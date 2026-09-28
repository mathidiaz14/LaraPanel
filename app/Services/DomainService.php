<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\User;
use App\Models\AuditLog;
use App\Notifications\DomainChangedNotification;
use App\Shell\SudoExecutor;
use App\Services\DnsService;
use App\Services\Notifier;
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
        protected NginxConfigGenerator $configGenerator,
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

        // Auto-crear el registro DNS A del subdominio en la zona del dominio padre
        if ($type === 'subdomain') {
            try {
                $this->createSubdomainDnsRecord($domain, $data['parent_domain'] ?? null);
            } catch (\Throwable $e) {
                \Log::error("Failed to auto-create DNS record for subdomain {$domain->name}: " . $e->getMessage());
            }
        }

        AuditLog::record('domain.created', $domainName, ['domain_id' => $domain->id]);

        Notifier::send(new DomainChangedNotification(
            'created',
            $domainName,
            $user->email ?: ''
        ));

        // Auto-provision uptime monitoring for the newly hosted site.
        try {
            app(\App\Services\UptimeProvisioner::class)->enrollDomain($domain);
        } catch (\Throwable $e) {
            \Log::error("Failed to auto-enroll uptime monitor for {$domain->name}: " . $e->getMessage());
        }

        return $domain->fresh();
    }

    /**
     * Create the DNS A record for a subdomain inside its parent zone.
     *
     * @param  Domain       $subdomain      The subdomain being provisioned.
     * @param  int|string|null $parentDomain   Parent domain id or name (from payload).
     */
    protected function createSubdomainDnsRecord(Domain $subdomain, $parentDomain = null): void
    {
        // Resolve the parent domain name (handles both numeric ids and names).
        $parentName = null;

        if (is_numeric($parentDomain)) {
            $parent = Domain::find((int) $parentDomain);
            $parentName = $parent?->name;
        } elseif (is_string($parentDomain) && $parentDomain !== '') {
            $parentName = $parentDomain;
        }

        // Fallback: derive the parent by stripping the first label (e.g. webmail. -> root).
        if (!$parentName) {
            $labels = explode('.', $subdomain->name);
            if (count($labels) >= 2) {
                $parentName = implode('.', array_slice($labels, 1));
            }
        }

        if (!$parentName) {
            return;
        }

        $zone = \App\Models\DnsZone::where('name', $parentName)->where('is_active', true)->first();

        if (!$zone) {
            \Log::warning("No DNS zone found for parent '{$parentName}' while creating subdomain {$subdomain->name}.");
            return;
        }

        // Relative record name (e.g. "webmail" / "recibos") within the zone.
        $relativeName = strtolower(substr($subdomain->name, 0, -strlen($parentName)));
        $relativeName = trim($relativeName, '.');

        if ($relativeName === '' || $relativeName === '@') {
            return;
        }

        $exists = \App\Models\DnsRecord::where('dns_zone_id', $zone->id)
            ->where('name', $relativeName)
            ->where('type', 'A')
            ->exists();

        if ($exists) {
            return;
        }

        $this->dns->createRecord($zone, [
            'name'     => $relativeName,
            'type'     => 'A',
            'content'  => config('larapanel.server.public_ip'),
            'ttl'      => 3600,
            'priority' => 0,
        ]);
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

        Notifier::send(new DomainChangedNotification(
            'deleted',
            $domain->name,
            optional($domain->user)->email ?: (auth()->check() ? auth()->user()->email : '')
        ));

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
                    $tmpFile = tempnam(sys_get_temp_dir(), 'lp_ssl_');
                    file_put_contents($tmpFile, $config);
                    $this->sudo->run(['cp', $tmpFile, "{$sitesAvail}/{$domain->name}"]);
                    $this->sudo->run(['ln', '-sf', "{$sitesAvail}/{$domain->name}", "{$sitesEnabled}/{$domain->name}"]);
                    @unlink($tmpFile);
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
    // Config Generation (delegated to NginxConfigGenerator)
    // ────────────────────────────────────────────────────────────────

    public function generateNginxConfig(Domain $domain): string
    {
        return $this->configGenerator->generateNginxConfig($domain);
    }

    public function generateNginxSslConfig(Domain $domain, string $certPath, string $keyPath): string
    {
        return $this->configGenerator->generateNginxSslConfig($domain, $certPath, $keyPath);
    }

    public function generateApacheConfig(Domain $domain): string
    {
        return $this->configGenerator->generateApacheConfig($domain);
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
        $tmpFile = tempnam(sys_get_temp_dir(), 'lp_vhost_');
        file_put_contents($tmpFile, $config);
        $this->sudo->run(['cp', $tmpFile, $confPath]);
        $this->sudo->run(['ln', '-sf', $confPath, "{$sitesEnabled}/{$filename}"]);
        @unlink($tmpFile);

        $domain->update(['config' => array_merge($domain->config ?? [], ['nginx' => $confPath])]);
    }

    protected function deployApacheConfig(Domain $domain): void
    {
        if (!app()->isProduction()) {
            return;
        }
        $config = $this->generateApacheConfig($domain);
        $path   = config('larapanel.paths.apache_sites') . '/' . $domain->name . '.conf';
        $tmpFile = tempnam(sys_get_temp_dir(), 'lp_apache_');
        file_put_contents($tmpFile, $config);
        $this->sudo->run(['cp', $tmpFile, $path]);
        $this->sudo->run(['a2ensite', $domain->name . '.conf']);
        @unlink($tmpFile);
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
