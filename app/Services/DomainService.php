<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use App\Services\DnsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DomainService — Manages web domain lifecycle.
 *
 * Responsibilities:
 * - Generate Nginx / Apache virtual host configuration files
 * - Create / remove document roots on the filesystem
 * - Enable / disable sites via symlinks (Nginx sites-enabled)
 * - Reload the web server after changes
 * - Track state in the domains DB table
 */
class DomainService
{
    public function __construct(
        protected SudoExecutor $sudo,
        protected DnsService $dns,
    ) {}

    // ────────────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────────────

    /**
     * Provision a new domain on the server.
     */
    public function create(User $user, array $data): Domain
    {
        $domainName  = strtolower(trim($data['name']));
        $phpVersion  = $data['php_version'] ?? config('larapanel.server.default_php');
        $webserver   = $data['webserver']   ?? config('larapanel.server.webserver');
        $documentRoot= $data['document_root'] ?? (config('larapanel.paths.webroots') . '/' . $domainName . '/public_html');
        $type        = $data['type'] ?? 'main';

        // 1. Persist to DB (status = provisioning)
        $domain = Domain::create([
            'user_id'       => $user->id,
            'name'          => $domainName,
            'type'          => $type,
            'parent_domain' => $data['parent_domain'] ?? null,
            'document_root' => $documentRoot,
            'php_version'   => $phpVersion,
            'webserver'     => $webserver,
            'status'        => 'pending',
        ]);

        // 2. Create document root directory
        $this->createDocumentRoot($documentRoot, $user);

        // 3. Generate and deploy virtual host config
        if ($webserver === 'nginx' || $webserver === 'both') {
            $this->deployNginxConfig($domain);
        }
        if ($webserver === 'apache' || $webserver === 'both') {
            $this->deployApacheConfig($domain);
        }

        // 4. Reload web server
        $this->reloadWebserver($webserver);

        // 5. Mark as active
        $domain->update([
            'status'      => 'active',
            'deployed_at' => now(),
            'is_active'   => true,
        ]);

        if ($type === 'main') {
            $zone = null;

            try {
                // Crea la zona con todos los registros base: A @/www/webmail/mail, MX, SPF y DMARC
                $zone = $this->dns->createZone($user, $domain);
            } catch (\Throwable $e) {
                \Log::error("Failed to automatically create DNS zone for domain {$domain->name}: " . $e->getMessage());
            }

            // Genera y publica DKIM (TXT mail._domainkey) y activa la firma en Rspamd
            try {
                $dkimService = app(DkimService::class);
                $dkimKey     = $dkimService->generateKeyPair($domain);

                if ($zone) {
                    $dkimService->deployToDns($dkimKey, $zone);
                    $dkimService->configureRspamdSigning($domain, $dkimKey);
                }
            } catch (\Throwable $e) {
                \Log::error("Failed to auto-configure DKIM for {$domain->name}: " . $e->getMessage());
            }

            // Auto-crear el subdominio de webmail para que aparezca en el panel y se le pueda asignar SSL
            try {
                $this->create($user, [
                    'name'          => 'webmail.' . $domainName,
                    'type'          => 'subdomain',
                    'parent_domain' => $domain->id,
                    'document_root' => '/usr/share/roundcube',
                    'php_version'   => $phpVersion,
                    'webserver'     => $webserver,
                ]);
            } catch (\Throwable $e) {
                \Log::error("Failed to auto-create webmail subdomain for {$domain->name}: " . $e->getMessage());
            }
        }

        AuditLog::record('domain.created', $domainName, ['domain_id' => $domain->id]);

        return $domain->fresh();
    }

    /**
     * Suspend a domain (disable its vhost without deleting files).
     */
    public function suspend(Domain $domain, string $reason = ''): void
    {
        $this->disableNginxSite($domain->name);
        $this->reloadWebserver($domain->webserver);

        $domain->update(['status' => 'suspended', 'is_active' => false]);

        AuditLog::record('domain.suspended', $domain->name, ['reason' => $reason]);
    }

    /**
     * Re-enable a suspended domain.
     */
    public function unsuspend(Domain $domain): void
    {
        $this->enableNginxSite($domain->name);
        $this->reloadWebserver($domain->webserver);

        $domain->update(['status' => 'active', 'is_active' => true]);

        AuditLog::record('domain.unsuspended', $domain->name);
    }

    /**
     * Delete a domain completely (files + vhost + DB record).
     */
    public function delete(Domain $domain, bool $deleteFiles = false): void
    {
        // Remove vhost config
        $this->removeNginxConfig($domain->name);

        // Optionally delete document root
        if ($deleteFiles) {
            $this->removeDocumentRoot($domain->document_root);
        }

        $this->reloadWebserver($domain->webserver);

        AuditLog::record('domain.deleted', $domain->name, ['files_deleted' => $deleteFiles]);

        $domain->forceDelete();
    }

    /**
     * Update the PHP version of a domain and redeploy configurations.
     */
    public function changePhpVersion(Domain $domain, string $phpVersion): void
    {
        AuditLog::record('domain.php.change', $domain->name, [
            'old_version' => $domain->php_version,
            'new_version' => $phpVersion,
        ]);

        $domain->update(['php_version' => $phpVersion]);
        $this->deployConfigs($domain);
    }

    /**
     * Deploy all configs for a domain (handles SSL vs non-SSL).
     */
    public function deployConfigs(Domain $domain): void
    {
        $webserver = $domain->webserver;
        if ($webserver === 'nginx' || $webserver === 'both') {
            $sslUsable = $domain->ssl_enabled
                && $domain->sslCertificate
                && is_file(config('larapanel.paths.ssl_certs') . '/' . $domain->name . '/fullchain.pem')
                && is_file(config('larapanel.paths.ssl_certs') . '/' . $domain->name . '/privkey.pem');

            if ($sslUsable) {
                $certDir  = config('larapanel.paths.ssl_certs') . '/' . $domain->name;
                $certFile = "{$certDir}/fullchain.pem";
                $keyFile  = "{$certDir}/privkey.pem";
                $config = $this->generateNginxSslConfig($domain, $certFile, $keyFile);
                
                if (!app()->isProduction()) {
                    $domain->update(['config' => array_merge($domain->config ?? [], ['nginx' => $config])]);
                } else {
                    $sitesAvail   = config('larapanel.paths.nginx_sites');
                    $sitesEnabled = config('larapanel.paths.nginx_enabled');
                    file_put_contents("/tmp/lp_ssl_{$domain->name}", $config);
                    $this->sudo->run(['cp', "/tmp/lp_ssl_{$domain->name}", "{$sitesAvail}/{$domain->name}"]);
                    $this->sudo->run(['ln', '-sf', "{$sitesAvail}/{$domain->name}", "{$sitesEnabled}/{$domain->name}"]);
                }
            } else {
                $this->deployNginxConfig($domain);
            }
        }
        if ($webserver === 'apache' || $webserver === 'both') {
            $this->deployApacheConfig($domain);
        }
        $this->reloadWebserver($webserver);
    }


    // ────────────────────────────────────────────────────────────────
    // Nginx Config Generation
    // ────────────────────────────────────────────────────────────────

    public function generateNginxConfig(Domain $domain): string
    {
        $phpSocket = $this->phpFpmSocket($domain->php_version);
        $root      = $domain->document_root;
        $name      = $domain->name;

        // ── Phase 10 performance settings ────────────────────────────
        $perf = $domain->performanceSetting;

        $locationBlock    = $this->buildLocationBlock($domain, $phpSocket, $perf);
        $phase10Headers   = $this->buildPhase10Headers($name, $perf);
        $attackBlock      = $this->buildAttackBlock($name, $perf);
        $geoWafBlock      = $this->buildGeoWafBlock($name, $perf);
        $redirectBlocks   = $this->buildRedirectBlocks($perf);
        $microcacheZone   = $this->buildMicrocacheZoneDirective($name, $perf);
        $performanceZones = $this->buildPerformanceZones($name, $perf);
        $brotliDirective  = $this->buildBrotliDirective($perf);
        $webmailRedirect  = $this->buildWebmailRedirectBlock($name, $root);

        return <<<NGINX
        # LaraPanel — generated for {$name}
        # DO NOT EDIT MANUALLY — changes will be overwritten
        {$microcacheZone}
        {$performanceZones}
        server {
            listen 80;
            listen [::]:80;
        
            server_name {$name} www.{$name};
            root {$root};
            index index.php index.html index.htm;
        
            access_log /var/log/nginx/{$name}.access.log;
            error_log  /var/log/nginx/{$name}.error.log;
        {$attackBlock}
        {$geoWafBlock}
            # Security headers
            add_header X-Frame-Options "SAMEORIGIN" always;
            add_header X-Content-Type-Options "nosniff" always;
            add_header X-XSS-Protection "1; mode=block" always;
            add_header Referrer-Policy "strict-origin-when-cross-origin" always;
        {$phase10Headers}
            # Gzip
            gzip on;
            gzip_types text/plain text/css application/json application/javascript text/xml;
        {$brotliDirective}
        {$redirectBlocks}
        {$locationBlock}
        {$webmailRedirect}
        
            # Let's Encrypt challenge
            location ^~ /.well-known/acme-challenge/ {
                root /var/www/letsencrypt;
                default_type "text/plain";
            }
        
            client_max_body_size 100M;
        }
        NGINX;
    }

    public function generateNginxSslConfig(Domain $domain, string $certPath, string $keyPath): string
    {
        $phpSocket = $this->phpFpmSocket($domain->php_version);
        $root      = $domain->document_root;
        $name      = $domain->name;

        $perf = $domain->performanceSetting;

        $locationBlock  = $this->buildLocationBlock($domain, $phpSocket, $perf);
        $phase10Headers = $this->buildPhase10Headers($name, $perf);
        $attackBlock    = $this->buildAttackBlock($name, $perf);
        $geoWafBlock    = $this->buildGeoWafBlock($name, $perf);
        $redirectBlocks = $this->buildRedirectBlocks($perf);
        $microcacheZone   = $this->buildMicrocacheZoneDirective($name, $perf);
        $performanceZones = $this->buildPerformanceZones($name, $perf);
        $hstsHeader       = $this->buildHstsHeader($perf);
        $hstsLine         = $hstsHeader
            ? "    add_header Strict-Transport-Security \"{$hstsHeader}\" always;"
            : '';
        $brotliDirective  = $this->buildBrotliDirective($perf);
        $webmailRedirect  = $this->buildWebmailRedirectBlock($name, $root);

        return <<<NGINX
        # LaraPanel SSL — generated for {$name}
        {$microcacheZone}
        {$performanceZones}
        server {
            listen 80;
            listen [::]:80;
            server_name {$name} www.{$name};
            return 301 https://\$host\$request_uri;
        }
        
        server {
            listen 443 ssl http2;
            listen [::]:443 ssl http2;
        
            server_name {$name} www.{$name};
            root {$root};
            index index.php index.html;
        
            ssl_certificate     {$certPath};
            ssl_certificate_key {$keyPath};
            ssl_protocols       TLSv1.2 TLSv1.3;
            ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384;
            ssl_prefer_server_ciphers off;
            ssl_session_cache   shared:SSL:10m;
            ssl_session_timeout 1d;
            ssl_stapling        on;
            ssl_stapling_verify on;
        {$attackBlock}
        {$geoWafBlock}
        {$hstsLine}
        {$brotliDirective}
            add_header X-Frame-Options "SAMEORIGIN" always;
            add_header X-Content-Type-Options "nosniff" always;
        {$phase10Headers}
        {$redirectBlocks}
        {$locationBlock}
        {$webmailRedirect}
        
            client_max_body_size 100M;
        }
        NGINX;
    }

    // ────────────────────────────────────────────────────────────────
    // Phase 10 — Nginx Block Builders
    // ────────────────────────────────────────────────────────────────

    /**
     * Build the location block supporting both PHP-FPM, legacy proxy port,
     * and Phase 10 Orange Cloud full-URL proxy.
     */
    protected function buildLocationBlock(
        Domain $domain,
        string $phpSocket,
        ?\App\Models\DomainPerformanceSetting $perf
    ): string {
        // Phase 10.4 Orange Cloud proxy (full URL target takes precedence)
        if ($perf && $perf->orange_cloud && $perf->proxy_target) {
            $target  = rtrim($perf->proxy_target, '/');
            $timeout = $perf->proxy_timeout ?? 60;
            $ws      = $perf->proxy_websocket ? <<<WS
                proxy_set_header Upgrade \$http_upgrade;
                proxy_set_header Connection 'upgrade';
            WS : '';
            return <<<LOC
            location / {
                proxy_pass {$target};
                proxy_http_version 1.1;
                proxy_set_header Host \$host;
                proxy_cache_bypass \$http_upgrade;
                proxy_set_header X-Real-IP \$remote_addr;
                proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
                proxy_set_header X-Forwarded-Proto \$scheme;
                proxy_connect_timeout {$timeout}s;
                proxy_send_timeout    {$timeout}s;
                proxy_read_timeout    {$timeout}s;
            {$ws}
            }
            LOC;
        }

        // Legacy proxy via config['proxy_port']
        $isProxy   = $domain->isProxy();
        $proxyPort = $domain->getProxyPort();

        if ($isProxy && $proxyPort) {
            return <<<LOC
            location / {
                proxy_pass http://127.0.0.1:{$proxyPort};
                proxy_http_version 1.1;
                proxy_set_header Upgrade \$http_upgrade;
                proxy_set_header Connection 'upgrade';
                proxy_set_header Host \$host;
                proxy_cache_bypass \$http_upgrade;
                proxy_set_header X-Real-IP \$remote_addr;
                proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
                proxy_set_header X-Forwarded-Proto \$scheme;
            }
            LOC;
        }

        // PHP-FPM with optional microcache
        $cacheDirectives = '';
        if ($perf && $perf->microcache_enabled) {
            $token = $this->zoneToken($domain->name);
            $cacheDirectives = <<<CACHE
                fastcgi_cache cache_{$token};
                fastcgi_cache_valid 200 301 302 {$perf->microcache_ttl}s;
                fastcgi_cache_use_stale error timeout updating;
                fastcgi_cache_bypass \$http_pragma;
                add_header X-Cache-Status \$upstream_cache_status;
            CACHE;
        }

        // Roundcube hardening: denegar instalador y directorio SQL (solo cuando el
        // document_root apunta a Roundcube, p.ej. subdominios webmail.*)
        $roundcubeGuards = str_contains($domain->document_root, 'roundcube') ? <<<GUARDS

        location ^~ /installer {
            deny all;
        }

        location ^~ /SQL {
            deny all;
        }
        GUARDS : '';

        return <<<LOC
        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }
        {$roundcubeGuards}
    
        location ~ \.php$ {
            fastcgi_pass unix:{$phpSocket};
            fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_hide_header X-Powered-By;
        {$cacheDirectives}
        }
    
        location ~ /\.(?!well-known).* {
            deny all;
        }
        LOC;
    }

    /**
     * Build the fastcgi_cache_path directive (placed above the server block).
     */
    protected function buildMicrocacheZoneDirective(
        string $domainName,
        ?\App\Models\DomainPerformanceSetting $perf
    ): string {
        if (!$perf || !$perf->microcache_enabled) {
            return '';
        }
        $basePath = config('larapanel.performance.microcache_base_path', '/var/cache/nginx');
        $path     = "{$basePath}/{$domainName}";
        $token    = $this->zoneToken($domainName);

        return <<<NGINX
        fastcgi_cache_path {$path} levels=1:2 keys_zone=cache_{$token}:10m max_size=1g inactive=60m use_temp_path=off;
        NGINX;
    }

    /**
     * Build a location block that redirects /webmail (y /webmail/) al
     * subdominio webmail.<dominio> para iniciar sesión en Roundcube.
     * Se omite para los propios subdominios webmail.* (document_root roundcube).
     */
    protected function buildWebmailRedirectBlock(string $domainName, string $documentRoot): string
    {
        if (str_contains($documentRoot, 'roundcube')) {
            return '';
        }

        return <<<NGINX

        # Redirect dominio/webmail -> webmail.dominio (Roundcube login)
        location ^~ /webmail {
            return 301 https://webmail.{$domainName}/;
        }
        NGINX;
    }

    /**
     * Build http-context directives for Phase 10 (rate zones, geoip2 DB, maps).
     * MUST be placed above any server block (valid only in http {} context).
     */
    protected function buildPerformanceZones(
        string $domainName,
        ?\App\Models\DomainPerformanceSetting $perf
    ): string {
        if (!$perf) {
            return '';
        }

        $token = $this->zoneToken($domainName);
        $lines = [];

        // 10.1 Under Attack Mode: shared zones (http context)
        if ($perf->under_attack_mode) {
            $rate = $perf->attack_rate ?? 10;
            $lines[] = '';
            $lines[] = '# LaraPanel — Under Attack Mode zones (10.1)';
            $lines[] = "limit_req_zone \$binary_remote_addr zone=attack_{$token}:10m rate={$rate}r/s;";
            $lines[] = "limit_conn_zone \$binary_remote_addr zone=conn_attack_{$token}:10m;";
        }

        // 10.3 Geo-WAF: geoip2 database + country map (http context)
        if ($perf->geo_waf_enabled && !empty($perf->geo_waf_countries)) {
            $mmdb = config('larapanel.geowaf.mmdb_path');
            $mode = $perf->geo_waf_mode === 'allow' ? 'allow' : 'block';

            // Value 1 marks a country as BLOCKED.
            // - block mode: listed countries are blocked (1), default 0
            // - allow mode: unlisted countries are blocked (1), default 1, listed 0
            $blockedValue = $mode === 'block' ? '1' : '0';
            $defaultValue = $mode === 'block' ? '0' : '1';

            $lines[] = '';
            $lines[] = "# LaraPanel — Geo-WAF ({$mode} mode) (10.3)";
            $lines[] = "geoip2 {$mmdb} { \$geoip2_data_country_iso_code default \"\" source=\$remote_addr country iso_code; }";
            $lines[] = "map \$geoip2_data_country_iso_code \$blocked_country_{$token} {";
            $lines[] = "    default {$defaultValue};";
            foreach ($perf->geo_waf_countries as $code) {
                $code = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $code) ?? '');
                if (strlen($code) === 2) {
                    $lines[] = "    {$code} {$blockedValue};";
                }
            }
            $lines[] = "}";
        }

        return $lines ? "\n" . implode("\n", $lines) . "\n" : '';
    }

    /**
     * Build Under Attack Mode enforcement directives (valid inside a server block).
     */
    protected function buildAttackBlock(
        string $domainName,
        ?\App\Models\DomainPerformanceSetting $perf
    ): string {
        if (!$perf || !$perf->under_attack_mode) {
            return '';
        }
        $burst = $perf->attack_burst ?? 20;
        $conn  = $perf->attack_conn ?? 10;
        $token = $this->zoneToken($domainName);

        return <<<NGINX

            # LaraPanel — Under Attack Mode (10.1)
            limit_req zone=attack_{$token} burst={$burst} nodelay;
            limit_conn conn_attack_{$token} {$conn};
        NGINX;
    }

    /**
     * Build Geo-WAF enforcement block (valid inside a server block).
     * The geoip2 DB + map live in http context via buildPerformanceZones().
     */
    protected function buildGeoWafBlock(
        string $domainName,
        ?\App\Models\DomainPerformanceSetting $perf
    ): string {
        if (!$perf || !$perf->geo_waf_enabled || empty($perf->geo_waf_countries)) {
            return '';
        }
        $token = $this->zoneToken($domainName);

        return <<<NGINX

            # LaraPanel — Geo-WAF (10.3)
            if (\$blocked_country_{$token}) {
                return 403 "Access Denied by Geo-WAF";
            }
        NGINX;
    }

    /**
     * Build extra security / custom headers for Phase 10.6.
     */
    protected function buildPhase10Headers(
        string $domainName,
        ?\App\Models\DomainPerformanceSetting $perf
    ): string {
        if (!$perf) return '';

        $lines = [];

        // Custom headers (sanitized: block newlines and control chars)
        foreach (($perf->custom_headers ?? []) as $header) {
            $hName  = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($header['name'] ?? ''));
            $hValue = str_replace(["\r", "\n"], '', (string) ($header['value'] ?? ''));
            if ($hName !== '' && $hValue !== '') {
                $lines[] = "    add_header {$hName} \"{$hValue}\" always;";
            }
        }

        return $lines ? "\n" . implode("\n", $lines) . "\n" : '';
    }

    /**
     * Build the HSTS header value string. Empty when the toggle is off.
     */
    protected function buildHstsHeader(?\App\Models\DomainPerformanceSetting $perf): string
    {
        if (!$perf || !$perf->hsts_enabled) {
            return '';
        }
        return $perf->hstsHeaderValue();
    }

    /**
     * Build brotli compression directives (server context, 10.6).
     * Requires the ngx_http_brotli module on the server.
     */
    protected function buildBrotliDirective(?\App\Models\DomainPerformanceSetting $perf): string
    {
        if (!$perf || !$perf->brotli_enabled) {
            return '';
        }

        return <<<NGINX

            # LaraPanel — Brotli (10.6)
            brotli on;
            brotli_comp_level 6;
            brotli_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
        NGINX;
    }

    /**
     * Sanitize a domain name into an nginx-safe identifier (zones, map variables).
     */
    protected function zoneToken(string $domainName): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $domainName) ?: 'domain';
    }

    /**
     * Build location blocks for custom 301/302 redirects (Page Rules 10.6).
     */
    protected function buildRedirectBlocks(?\App\Models\DomainPerformanceSetting $perf): string
    {
        if (!$perf || empty($perf->redirects)) {
            return '';
        }

        $blocks = [];
        foreach ($perf->redirects as $rule) {
            $from = $rule['from'] ?? '';
            $to   = $rule['to']   ?? '';
            $code = in_array((int)($rule['code'] ?? 301), [301, 302]) ? (int)$rule['code'] : 301;
            if ($from && $to) {
                $blocks[] = <<<LOC
            location {$from} {
                return {$code} {$to};
            }
            LOC;
            }
        }

        return $blocks ? "\n" . implode("\n", $blocks) . "\n" : '';
    }

    // ────────────────────────────────────────────────────────────────
    // Phase 10 — Performance Setting Management
    // ────────────────────────────────────────────────────────────────

    /**
     * Toggle Under Attack Mode for a domain and redeploy its vhost.
     */
    public function toggleUnderAttackMode(Domain $domain, bool $enable, array $options = []): void
    {
        $domain->getPerformance()->update(array_merge([
            'under_attack_mode' => $enable,
        ], array_intersect_key($options, array_flip(['attack_rate', 'attack_burst', 'attack_conn']))));

        $domain->refresh();
        $this->deployConfigs($domain);

        AuditLog::record(
            $enable ? 'domain.under_attack.enabled' : 'domain.under_attack.disabled',
            $domain->name
        );
    }

    /**
     * Enable / update FastCGI microcaching for a domain.
     */
    public function enableMicrocache(Domain $domain, int $ttl = 60): void
    {
        $domain->getPerformance()->update(['microcache_enabled' => true, 'microcache_ttl' => $ttl]);
        $domain->refresh();
        $this->deployConfigs($domain);
        AuditLog::record('domain.microcache.enabled', $domain->name, ['ttl' => $ttl]);
    }

    /**
     * Disable FastCGI microcaching for a domain.
     */
    public function disableMicrocache(Domain $domain): void
    {
        $domain->getPerformance()->update(['microcache_enabled' => false]);
        $domain->refresh();
        $this->deployConfigs($domain);
        AuditLog::record('domain.microcache.disabled', $domain->name);
    }

    /**
     * Purge the on-disk microcache for a domain.
     */
    public function purgeMicrocache(Domain $domain): void
    {
        $basePath = config('larapanel.performance.microcache_base_path', '/var/cache/nginx');
        $cachePath = "{$basePath}/{$domain->name}";

        if (app()->isProduction()) {
            $this->sudo->run(['rm', '-rf', $cachePath], checkExit: false);
            $this->sudo->run(['mkdir', '-p', $cachePath]);
        }

        $domain->getPerformance()->update(['microcache_purged_at' => now()]);
        AuditLog::record('domain.microcache.purged', $domain->name);
    }

    /**
     * Save Page Rules (HSTS + custom headers + redirects) for a domain.
     */
    public function savePageRules(Domain $domain, array $data): void
    {
        $domain->getPerformance()->update(array_intersect_key($data, array_flip([
            'hsts_enabled', 'hsts_max_age', 'hsts_include_subdomains', 'hsts_preload',
            'custom_headers', 'redirects', 'brotli_enabled',
        ])));

        $domain->refresh();
        $this->deployConfigs($domain);
        AuditLog::record('domain.page_rules.saved', $domain->name);
    }

    /**
     * Save Orange Cloud (reverse proxy) settings for a domain.
     */
    public function saveProxyConfig(Domain $domain, array $data): void
    {
        $domain->getPerformance()->update(array_intersect_key($data, array_flip([
            'orange_cloud', 'proxy_target', 'proxy_ssl_verify', 'proxy_timeout', 'proxy_websocket',
        ])));

        $domain->refresh();
        $this->deployConfigs($domain);
        AuditLog::record('domain.proxy.configured', $domain->name, ['target' => $data['proxy_target'] ?? null]);
    }

    /**
     * Save Geo-WAF settings for a domain.
     */
    public function saveGeoWaf(Domain $domain, array $data): void
    {
        $domain->getPerformance()->update(array_intersect_key($data, array_flip([
            'geo_waf_enabled', 'geo_waf_mode', 'geo_waf_countries',
        ])));

        $domain->refresh();
        $this->deployConfigs($domain);
        AuditLog::record('domain.geowaf.saved', $domain->name, ['countries' => $data['geo_waf_countries'] ?? []]);
    }

    // ────────────────────────────────────────────────────────────────
    // Apache Config Generation
    // ────────────────────────────────────────────────────────────────

    public function generateApacheConfig(Domain $domain): string
    {
        $root = $domain->document_root;
        $name = $domain->name;
        $php  = $domain->php_version;

        return <<<APACHE
        # LaraPanel — generated for {$name}
        <VirtualHost *:80>
            ServerName {$name}
            ServerAlias www.{$name}
            DocumentRoot {$root}
        
            <FilesMatch \.php$>
                SetHandler "proxy:unix:/run/php/php{$php}-fpm.sock|fcgi://localhost"
            </FilesMatch>
        
            <Directory {$root}>
                Options -Indexes +FollowSymLinks
                AllowOverride All
                Require all granted
            </Directory>
        
            ErrorLog  \${APACHE_LOG_DIR}/{$name}.error.log
            CustomLog \${APACHE_LOG_DIR}/{$name}.access.log combined
        </VirtualHost>
        APACHE;
    }

    // ────────────────────────────────────────────────────────────────
    // Filesystem Operations
    // ────────────────────────────────────────────────────────────────

    protected function createDocumentRoot(string $path, User $user): void
    {
        // Nunca tocar ni alterar el directorio core de Roundcube
        if ($path === '/usr/share/roundcube') {
            return;
        }

        // In production: use sudo mkdir + chown
        // In development: just create the directory via PHP
        if (!app()->isProduction()) {
            @mkdir($path, 0755, true);
            // Create a default index.html
            @file_put_contents($path . '/index.html',
                '<html><body><h1>Domain provisioned by LaraPanel</h1></body></html>'
            );
            return;
        }

        $this->sudo->run(['mkdir', '-p', $path]);
        $this->sudo->run(['chown', '-R', "www-data:www-data", $path]);
        $this->sudo->run(['chmod', '755', $path]);

        // Crear un index.html por defecto para que no de error 404 al inicio
        $defaultHtml = '<html><head><title>Dominio Creado</title><style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background:#f3f4f6;margin:0;}div{text-align:center;padding:2rem;background:#fff;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);}</style></head><body><div><h1>¡Tu dominio está listo!</h1><p>El dominio ha sido configurado correctamente en LaraPanel.</p></div></body></html>';
        $tmpFile = sys_get_temp_dir() . '/lp_index_' . uniqid() . '.html';
        file_put_contents($tmpFile, $defaultHtml);
        $this->sudo->run(['cp', $tmpFile, $path . '/index.html']);
        $this->sudo->run(['chown', 'www-data:www-data', $path . '/index.html']);
        @unlink($tmpFile);
    }

    protected function removeDocumentRoot(string $path): void
    {
        // Nunca borrar el directorio core de Roundcube
        if ($path === '/usr/share/roundcube') {
            return;
        }

        // Safety check: never delete / or /var/www directly
        if (strlen($path) < 10 || $path === '/var/www') {
            throw new \RuntimeException("Refusing to delete unsafe path: {$path}");
        }

        if (!app()->isProduction()) {
            // Development: skip actual deletion
            return;
        }

        $this->sudo->run(['rm', '-rf', $path]);
    }

    protected function deployNginxConfig(Domain $domain): void
    {
        $config   = $this->generateNginxConfig($domain);
        $sitesAvail = config('larapanel.paths.nginx_sites');
        $sitesEnabled = config('larapanel.paths.nginx_enabled');
        $filename = $domain->name;

        if (!app()->isProduction()) {
            // In dev: just store the generated config content in DB
            $domain->update(['config' => array_merge($domain->config ?? [], ['nginx' => $config])]);
            return;
        }

        $confPath = "{$sitesAvail}/{$filename}";
        file_put_contents('/tmp/larapanel_vhost_' . $filename, $config);
        $this->sudo->run(['cp', '/tmp/larapanel_vhost_' . $filename, $confPath]);
        $this->sudo->run(['ln', '-sf', $confPath, "{$sitesEnabled}/{$filename}"]);

        $domain->update(['config' => array_merge($domain->config ?? [], ['nginx' => $confPath])]);
    }

    protected function deployApacheConfig(Domain $domain): void
    {
        if (!app()->isProduction()) {
            return;
        }
        $config = $this->generateApacheConfig($domain);
        $path   = config('larapanel.paths.apache_sites') . '/' . $domain->name . '.conf';
        file_put_contents('/tmp/lp_apache_' . $domain->name, $config);
        $this->sudo->run(['cp', '/tmp/lp_apache_' . $domain->name, $path]);
        $this->sudo->run(['a2ensite', $domain->name . '.conf']);
    }

    protected function enableNginxSite(string $domainName): void
    {
        if (!app()->isProduction()) return;
        $sitesAvail   = config('larapanel.paths.nginx_sites');
        $sitesEnabled = config('larapanel.paths.nginx_enabled');
        $this->sudo->run(['ln', '-sf', "{$sitesAvail}/{$domainName}", "{$sitesEnabled}/{$domainName}"]);
    }

    protected function disableNginxSite(string $domainName): void
    {
        if (!app()->isProduction()) return;
        $sitesEnabled = config('larapanel.paths.nginx_enabled');
        $this->sudo->run(['rm', '-f', "{$sitesEnabled}/{$domainName}"], checkExit: false);
    }

    protected function removeNginxConfig(string $domainName): void
    {
        if (!app()->isProduction()) return;
        $sitesAvail   = config('larapanel.paths.nginx_sites');
        $sitesEnabled = config('larapanel.paths.nginx_enabled');
        $this->sudo->run(['rm', '-f', "{$sitesEnabled}/{$domainName}"], checkExit: false);
        $this->sudo->run(['rm', '-f', "{$sitesAvail}/{$domainName}"], checkExit: false);
    }

    protected function reloadWebserver(string $webserver): void
    {
        if (!app()->isProduction()) return;

        try {
            if ($webserver === 'nginx' || $webserver === 'both') {
                $this->sudo->reloadNginx();
            }
            if ($webserver === 'apache' || $webserver === 'both') {
                $this->sudo->restartService('apache2');
            }
        } catch (\Throwable $e) {
            \Log::error('LaraPanel: Failed to reload webserver', ['error' => $e->getMessage()]);
        }
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    protected function phpFpmSocket(string $phpVersion): string
    {
        return "/run/php/php{$phpVersion}-fpm.sock";
    }

    public function getAvailablePhpVersions(): array
    {
        if (!app()->isProduction()) {
            return config('larapanel.server.php_versions');
        }

        $result = $this->sudo->run(['find', '/etc/php', '-name', 'php-fpm.conf'], checkExit: false);
        $versions = [];
        foreach ($result->lines() as $line) {
            if (preg_match('/\/etc\/php\/(\d+\.\d+)\//', $line, $m)) {
                $versions[] = $m[1];
            }
        }
        return $versions ?: config('larapanel.server.php_versions');
    }

    public function validateDomainName(string $name): bool
    {
        return (bool) preg_match(
            '/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
            $name
        );
    }
}
