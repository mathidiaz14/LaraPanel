<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainPerformanceSetting;

/**
 * NginxConfigGenerator — builds Nginx (and Apache) virtual host configuration
 * strings for LaraPanel domains.
 *
 * This class was extracted from DomainService to keep the domain lifecycle
 * service focused on orchestration (DB state, filesystem, DNS, reloads)
 * while all configuration rendering lives here.
 */
class NginxConfigGenerator
{
    // ────────────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────────────

    public function generateNginxConfig(Domain $domain): string
    {
        $phpSocket = $this->phpFpmSocket($domain->php_version);
        $root      = $domain->document_root;
        $name      = $domain->name;

        // ── Performance settings (Phase 10) ────────────────────────
        $perf            = $domain->performanceSetting;
        $locationBlock   = $this->buildLocationBlock($domain, $phpSocket, $perf);
        $phase10Headers  = $this->buildPhase10Headers($perf);
        $attackBlock     = $this->buildAttackBlock($name, $perf);
        $geoWafBlock     = $this->buildGeoWafBlock($name, $perf);
        $redirectBlocks  = $this->buildRedirectBlocks($perf);
        $microcacheZone  = $this->buildMicrocacheZoneDirective($name, $perf);
        $performanceZones = $this->buildPerformanceZones($name, $perf);
        $brotliDirective = $this->buildBrotliDirective($perf);
        $webmailRedirect = $this->buildWebmailRedirectBlock($name, $root);
        $errorPages      = $this->buildErrorPagesBlocks($domain);
        $upstreamBlock   = $this->buildUpstreamBlock($domain, $name);

        return <<<NGINX
        # LaraPanel — generated for {$name}
        # DO NOT EDIT MANUALLY — changes will be overwritten
        {$microcacheZone}
        {$performanceZones}
        {$upstreamBlock}
        server {
            listen 80;
            listen [::]:80;
        
            server_name {$name} www.{$name};
            root {$root};
            index index.php index.html index.htm;
        
            access_log /var/log/nginx/{$name}.access.log;
            error_log  /var/log/nginx/{$name}.error.log;
        {$errorPages}
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
        {$this->acmeChallengeBlock()}
            client_max_body_size 100M;
        }
        NGINX;
    }

    public function generateNginxSslConfig(Domain $domain, string $certPath, string $keyPath): string
    {
        $phpSocket = $this->phpFpmSocket($domain->php_version);
        $root      = $domain->document_root;
        $name      = $domain->name;

        $perf             = $domain->performanceSetting;
        $locationBlock    = $this->buildLocationBlock($domain, $phpSocket, $perf);
        $phase10Headers   = $this->buildPhase10Headers($perf);
        $attackBlock      = $this->buildAttackBlock($name, $perf);
        $geoWafBlock      = $this->buildGeoWafBlock($name, $perf);
        $redirectBlocks   = $this->buildRedirectBlocks($perf);
        $microcacheZone   = $this->buildMicrocacheZoneDirective($name, $perf);
        $performanceZones = $this->buildPerformanceZones($name, $perf);
        $hstsHeader       = $this->buildHstsHeader($perf);
        $hstsLine         = $hstsHeader
            ? "    add_header Strict-Transport-Security \"{$hstsHeader}\" always;"
            : '';
        $brotliDirective  = $this->buildBrotliDirective($perf);
        $webmailRedirect  = $this->buildWebmailRedirectBlock($name, $root);
        $errorPages       = $this->buildErrorPagesBlocks($domain);
        $upstreamBlock    = $this->buildUpstreamBlock($domain, $name);

        return <<<NGINX
        # LaraPanel SSL — generated for {$name}
        {$microcacheZone}
        {$performanceZones}
        {$upstreamBlock}
        server {
            listen 80;
            listen [::]:80;
            server_name {$name} www.{$name};
        {$this->acmeChallengeBlock()}
            location / {
                return 301 https://\$host\$request_uri;
            }
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
        {$errorPages}
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
        {$this->acmeChallengeBlock()}
            client_max_body_size 100M;
        }
        NGINX;
    }

    /**
     * ACME HTTP-01 challenge location.
     *
     * MUST be present on BOTH :80 and :443. After SSL is enabled the :80
     * server redirects to https, and Let's Encrypt (and acme.sh) follow the
     * redirect — so the challenge must be served in both server blocks or
     * renewals fail with a 404.
     */
    protected function acmeChallengeBlock(): string
    {
        return <<<'NGINX'
            # Let's Encrypt HTTP-01 challenge
            location ^~ /.well-known/acme-challenge/ {
                root /var/www/letsencrypt;
                default_type "text/plain";
            }
        NGINX;
    }

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
    // Location / upstream builders
    // ────────────────────────────────────────────────────────────────

    /**
     * Build an upstream block for the domain. Reserved for load-balancing
     * staging groups (generated via the staging/clustering features).
     */
    protected function buildUpstreamBlock(Domain $domain, string $domainName): string
    {
        $upstreams = $domain->getStagingUpstreams();

        if ($upstreams === []) {
            return '';
        }

        $token = $this->zoneToken($domainName);
        $members = collect($upstreams)->map(
            fn (array $up) => trim((string) ($up['address'] ?? ''))
        )->filter()->map(fn (string $addr) => "    server {$addr};")->implode("\n");

        if ($members === '') {
            return '';
        }

        return <<<NGINX
        # LaraPanel — Staging upstream group ({$token})
        upstream staging_{$token} {
        {$members}
        }
        NGINX;
    }

    /**
     * Build the location block supporting both PHP-FPM, legacy proxy port,
     * and Phase 10 Orange Cloud full-URL proxy.
     */
    protected function buildLocationBlock(
        Domain $domain,
        string $phpSocket,
        ?DomainPerformanceSetting $perf
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

        // Staging upstream group (load-balanced)
        if ($domain->usesStagingUpstream()) {
            $token = $this->zoneToken($domain->name);
            return <<<LOC
            location / {
                proxy_pass http://staging_{$token};
                proxy_http_version 1.1;
                proxy_set_header Host \$host;
                proxy_cache_bypass \$http_upgrade;
                proxy_set_header X-Real-IP \$remote_addr;
                proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
                proxy_set_header X-Forwarded-Proto \$scheme;
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

    // ────────────────────────────────────────────────────────────────
    // Custom error pages (302-safe)
    // ────────────────────────────────────────────────────────────────

    protected function buildErrorPagesBlocks(Domain $domain): string
    {
        $errorPages = is_array($domain->error_pages ?? null) ? $domain->error_pages : [];

        if ($errorPages === []) {
            return '';
        }

        $blocks = [];
        foreach ($errorPages as $code => $target) {
            $code = (int) $code;
            if (! in_array($code, [401, 403, 404, 500, 502, 503], true)) {
                continue;
            }
            $target = trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string) $target) ?? '');
            if ($target === '') {
                continue;
            }
            $blocks[] = "            error_page {$code} {$target};";
        }

        return $blocks ? "\n" . implode("\n", $blocks) . "\n" : '';
    }

    // ────────────────────────────────────────────────────────────────
    // Phase 10 — Nginx Block Builders
    // ────────────────────────────────────────────────────────────────

    /**
     * Build the fastcgi_cache_path directive (placed above the server block).
     */
    protected function buildMicrocacheZoneDirective(
        string $domainName,
        ?DomainPerformanceSetting $perf
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
        ?DomainPerformanceSetting $perf
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
        ?DomainPerformanceSetting $perf
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
        ?DomainPerformanceSetting $perf
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
    protected function buildPhase10Headers(?DomainPerformanceSetting $perf): string
    {
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
    protected function buildHstsHeader(?DomainPerformanceSetting $perf): string
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
    protected function buildBrotliDirective(?DomainPerformanceSetting $perf): string
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
    protected function buildRedirectBlocks(?DomainPerformanceSetting $perf): string
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
    // Helpers
    // ────────────────────────────────────────────────────────────────

    public function phpFpmSocket(string $phpVersion): string
    {
        return "/run/php/php{$phpVersion}-fpm.sock";
    }
}