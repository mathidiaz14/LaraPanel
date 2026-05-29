<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
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

        $domain->delete();
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
            if ($domain->ssl_enabled && $domain->sslCertificate) {
                $certDir  = config('larapanel.paths.ssl_certs') . '/' . $domain->name;
                $certFile = "{$certDir}/fullchain.pem";
                $keyFile  = "{$certDir}/privkey.pem";
                $config = $this->generateNginxSslConfig($domain, $certFile, $keyFile);
                
                if (!app()->isProduction()) {
                    $domain->update(['config' => ['nginx' => $config]]);
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

        return <<<NGINX
        # LaraPanel — generated for {$name}
        # DO NOT EDIT MANUALLY — changes will be overwritten
        
        server {
            listen 80;
            listen [::]:80;
        
            server_name {$name} www.{$name};
            root {$root};
            index index.php index.html index.htm;
        
            access_log /var/log/nginx/{$name}.access.log;
            error_log  /var/log/nginx/{$name}.error.log;
        
            # Security headers
            add_header X-Frame-Options "SAMEORIGIN" always;
            add_header X-Content-Type-Options "nosniff" always;
            add_header X-XSS-Protection "1; mode=block" always;
            add_header Referrer-Policy "strict-origin-when-cross-origin" always;
        
            # Gzip
            gzip on;
            gzip_types text/plain text/css application/json application/javascript text/xml;
        
            location / {
                try_files \$uri \$uri/ /index.php?\$query_string;
            }
        
            location ~ \.php$ {
                fastcgi_pass unix:{$phpSocket};
                fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
                include fastcgi_params;
                fastcgi_hide_header X-Powered-By;
            }
        
            location ~ /\.(?!well-known).* {
                deny all;
            }
        
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

        return <<<NGINX
        # LaraPanel SSL — generated for {$name}
        
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
        
            add_header Strict-Transport-Security "max-age=63072000" always;
            add_header X-Frame-Options "SAMEORIGIN" always;
            add_header X-Content-Type-Options "nosniff" always;
        
            location / {
                try_files \$uri \$uri/ /index.php?\$query_string;
            }
        
            location ~ \.php$ {
                fastcgi_pass unix:{$phpSocket};
                fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
                include fastcgi_params;
            }
        
            location ~ /\.(?!well-known).* {
                deny all;
            }
        
            client_max_body_size 100M;
        }
        NGINX;
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
    }

    protected function removeDocumentRoot(string $path): void
    {
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
            $domain->update(['config' => ['nginx' => $config]]);
            return;
        }

        $confPath = "{$sitesAvail}/{$filename}";
        file_put_contents('/tmp/larapanel_vhost_' . $filename, $config);
        $this->sudo->run(['cp', '/tmp/larapanel_vhost_' . $filename, $confPath]);
        $this->sudo->run(['ln', '-sf', $confPath, "{$sitesEnabled}/{$filename}"]);

        $domain->update(['config' => ['nginx' => $confPath]]);
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
