<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\SslCertificate;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * SslService — Manages SSL/TLS certificates.
 *
 * Supports:
 * 1. Let's Encrypt via acme.sh (preferred) or certbot fallback
 * 2. Custom (externally-purchased) certificate installation
 * 3. Self-signed certificate generation (for internal/dev use)
 * 4. Auto-renewal detection and triggering
 */
class SslService
{
    // acme.sh is simpler, rootless, and works better with nginx reload
    protected string $acmeSh = '/root/.acme.sh/acme.sh';

    public function __construct(
        protected SudoExecutor    $sudo,
        protected DomainService   $domains,
    ) {}

    // ────────────────────────────────────────────────────────────────
    // Let's Encrypt — Issue Certificate
    // ────────────────────────────────────────────────────────────────

    /**
     * Issue a Let's Encrypt certificate using the webroot challenge.
     * Works even with Nginx running (no downtime).
     *
     * @param  Domain  $domain
     * @param  array   $sanDomains  Additional SANs e.g. ['www.example.com']
     * @param  bool    $includeWww  Auto-add www. variant
     */
    public function issueLetsEncrypt(Domain $domain, array $sanDomains = [], bool $includeWww = true): SslCertificate
    {
        // Build the full SAN list
        $allDomains = [$domain->name];
        if ($includeWww && !str_starts_with($domain->name, 'www.')) {
            $allDomains[] = 'www.' . $domain->name;
        }
        $allDomains = array_unique(array_merge($allDomains, $sanDomains));

        // Create or update the DB record in pending state
        $cert = SslCertificate::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'provider'    => 'letsencrypt',
                'status'      => 'pending',
                'auto_renew'  => true,
                'san_domains' => $allDomains,
                'last_error'  => null,
            ]
        );

        AuditLog::record('ssl.letsencrypt.started', $domain->name, ['domains' => $allDomains]);

        try {
            if (app()->isProduction()) {
                $this->runAcmeSh($domain, $allDomains, $cert);
            } else {
                // Development mode: simulate certificate issuance
                $this->simulateCertificate($domain, $cert, 'letsencrypt');
            }

            // Deploy SSL nginx config
            $this->deploySslNginxConfig($domain, $cert);

            AuditLog::record('ssl.letsencrypt.issued', $domain->name, [
                'expires_at' => $cert->fresh()->expires_at?->toIso8601String(),
            ]);

        } catch (\Throwable $e) {
            $cert->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            AuditLog::record('ssl.letsencrypt.failed', $domain->name, ['error' => $e->getMessage()], severity: 'critical');
            throw $e;
        }

        return $cert->fresh();
    }

    /**
     * Run acme.sh to issue the certificate (production only).
     */
    protected function runAcmeSh(Domain $domain, array $allDomains, SslCertificate $cert): void
    {
        // Build -d flags
        $dFlags = implode(' ', array_map(fn($d) => "-d {$d}", $allDomains));

        // Webroot directory for ACME challenge
        $webrootPath = '/var/www/letsencrypt';
        $this->sudo->run(['mkdir', '-p', $webrootPath]);

        // Issue via acme.sh webroot method
        $result = $this->sudo->run([
            $this->acmeSh, '--issue',
            '--webroot', $webrootPath,
            ...array_merge(...array_map(fn($d) => ['-d', $d], $allDomains)),
            '--server', 'letsencrypt',
            '--force',
        ], checkExit: false);

        if ($result->failed() && !str_contains($result->stdout, 'Cert success')) {
            throw new \RuntimeException("acme.sh failed: " . $result->stderr);
        }

        // Install cert to our managed path
        $certDir = config('larapanel.paths.ssl_certs') . '/' . $domain->name;
        $this->sudo->run(['mkdir', '-p', $certDir]);

        $certFile = "{$certDir}/fullchain.pem";
        $keyFile  = "{$certDir}/privkey.pem";
        $chainFile = "{$certDir}/chain.pem";

        $this->sudo->run([
            $this->acmeSh, '--install-cert',
            '-d', $domain->name,
            '--cert-file',      "{$certDir}/cert.pem",
            '--key-file',       $keyFile,
            '--fullchain-file', $certFile,
            '--ca-file',        $chainFile,
            '--reloadcmd',      'systemctl reload nginx',
        ]);

        // Asegurar que PHP pueda leer los archivos generados por root (acme.sh)
        $this->sudo->run(['chown', '-R', 'www-data:www-data', $certDir]);
        $this->sudo->run(['chmod', '600', $keyFile]);

        // Read the issued cert to extract expiry
        $certContent  = file_get_contents($certFile);
        $keyContent   = file_get_contents($keyFile);
        $chainContent = file_get_contents($chainFile);
        $expiry       = $this->parseCertExpiry($certContent);

        $cert->update([
            'status'      => 'active',
            'certificate' => $certContent,
            'private_key' => Crypt::encryptString($keyContent),
            'chain'       => $chainContent,
            'issued_at'   => now(),
            'expires_at'  => $expiry,
        ]);

        // Update domain SSL fields
        $domain->update([
            'ssl_enabled'   => true,
            'ssl_expires_at'=> $expiry,
            'ssl_provider'  => 'letsencrypt',
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Custom Certificate Installation
    // ────────────────────────────────────────────────────────────────

    /**
     * Install a custom (externally-purchased) SSL certificate.
     *
     * @param  Domain  $domain
     * @param  string  $certificate  PEM-encoded certificate
     * @param  string  $privateKey   PEM-encoded private key
     * @param  string  $chain        PEM-encoded CA chain (optional)
     */
    public function installCustomCertificate(
        Domain $domain,
        string $certificate,
        string $privateKey,
        string $chain = '',
    ): SslCertificate {
        // Validate the certificate/key pair
        $this->validateCertificateKeyPair($certificate, $privateKey);

        // Extract expiry from the certificate
        $expiry = $this->parseCertExpiry($certificate);

        // Validate expiry
        if ($expiry && $expiry->isPast()) {
            throw new \RuntimeException('El certificado ya está expirado (' . $expiry->format('d/m/Y') . ').');
        }

        // Validate the cert matches the domain
        $this->validateCertDomain($certificate, $domain->name);

        // Save to DB (key is encrypted at rest)
        $cert = SslCertificate::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'provider'    => 'custom',
                'status'      => 'active',
                'certificate' => $certificate,
                'private_key' => Crypt::encryptString($privateKey),
                'chain'       => $chain ?: null,
                'issued_at'   => now(),
                'expires_at'  => $expiry,
                'auto_renew'  => false,
                'san_domains' => [$domain->name],
                'last_error'  => null,
            ]
        );

        // Deploy to disk and reload nginx
        $this->writeAndDeployCert($domain, $certificate, $privateKey, $chain);
        $this->deploySslNginxConfig($domain, $cert);

        $domain->update([
            'ssl_enabled'    => true,
            'ssl_expires_at' => $expiry,
            'ssl_provider'   => 'custom',
        ]);

        AuditLog::record('ssl.custom.installed', $domain->name, [
            'expires_at' => $expiry?->toIso8601String(),
        ]);

        return $cert->fresh();
    }

    // ────────────────────────────────────────────────────────────────
    // Self-Signed Certificate
    // ────────────────────────────────────────────────────────────────

    public function generateSelfSigned(Domain $domain): SslCertificate
    {
        AuditLog::record('ssl.selfsigned.generating', $domain->name);

        if (!app()->isProduction()) {
            $cert = SslCertificate::updateOrCreate(
                ['domain_id' => $domain->id],
                [
                    'provider'    => 'selfsigned',
                    'status'      => 'active',
                    'certificate' => '(dev-mode: self-signed placeholder)',
                    'private_key' => Crypt::encryptString('(dev-mode-key)'),
                    'issued_at'   => now(),
                    'expires_at'  => now()->addYear(),
                    'auto_renew'  => false,
                    'san_domains' => [$domain->name],
                ]
            );
            $domain->update(['ssl_enabled' => true, 'ssl_expires_at' => now()->addYear(), 'ssl_provider' => 'selfsigned']);
            return $cert;
        }

        $certDir = config('larapanel.paths.ssl_certs') . '/' . $domain->name;
        $this->sudo->run(['mkdir', '-p', $certDir]);

        $this->sudo->run([
            'openssl', 'req', '-x509', '-nodes', '-newkey', 'rsa:2048',
            '-keyout', "{$certDir}/privkey.pem",
            '-out',    "{$certDir}/fullchain.pem",
            '-days',   '365',
            '-subj',   "/CN={$domain->name}/O=LaraPanel/C=US",
            '-addext', "subjectAltName=DNS:{$domain->name},DNS:www.{$domain->name}",
        ]);

        // Asegurar que PHP pueda leer los archivos generados por root (openssl)
        $this->sudo->run(['chown', '-R', 'www-data:www-data', $certDir]);
        $this->sudo->run(['chmod', '600', "{$certDir}/privkey.pem"]);

        $certContent = file_get_contents("{$certDir}/fullchain.pem");
        $keyContent  = file_get_contents("{$certDir}/privkey.pem");

        $cert = SslCertificate::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'provider'    => 'selfsigned',
                'status'      => 'active',
                'certificate' => $certContent,
                'private_key' => Crypt::encryptString($keyContent),
                'issued_at'   => now(),
                'expires_at'  => now()->addYear(),
                'auto_renew'  => false,
                'san_domains' => [$domain->name],
            ]
        );

        $this->deploySslNginxConfig($domain, $cert);
        $domain->update(['ssl_enabled' => true, 'ssl_expires_at' => now()->addYear(), 'ssl_provider' => 'selfsigned']);

        return $cert;
    }

    // ────────────────────────────────────────────────────────────────
    // Revoke / Remove
    // ────────────────────────────────────────────────────────────────

    public function revoke(Domain $domain): void
    {
        $cert = $domain->sslCertificate;
        if (!$cert) return;

        if (app()->isProduction() && $cert->provider === 'letsencrypt') {
            $this->sudo->run([
                $this->acmeSh, '--revoke', '-d', $domain->name,
            ], checkExit: false);
        }

        $cert->update(['status' => 'revoked']);
        $domain->update([
            'ssl_enabled'    => false,
            'ssl_expires_at' => null,
            'ssl_provider'   => null,
        ]);

        // Revert to plain HTTP config and reload webserver
        $this->domains->deployConfigs($domain);

        AuditLog::record('ssl.revoked', $domain->name);
    }

    // ────────────────────────────────────────────────────────────────
    // Auto-Renewal (called by Laravel Scheduler)
    // ────────────────────────────────────────────────────────────────

    /**
     * Renew all Let's Encrypt certs expiring within 30 days.
     */
    public function renewAll(): array
    {
        $results = ['renewed' => [], 'failed' => [], 'skipped' => []];

        $expiring = SslCertificate::where('provider', 'letsencrypt')
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->where('expires_at', '<=', now()->addDays(30))
            ->with('domain')
            ->get();

        foreach ($expiring as $cert) {
            if (!$cert->domain) {
                $results['skipped'][] = $cert->id;
                continue;
            }
            try {
                $this->issueLetsEncrypt($cert->domain, $cert->san_domains ?? []);
                $cert->update(['last_renewed_at' => now()]);
                $results['renewed'][] = $cert->domain->name;
            } catch (\Throwable $e) {
                $results['failed'][] = ['domain' => $cert->domain->name, 'error' => $e->getMessage()];
                Log::error('SSL auto-renew failed', ['domain' => $cert->domain->name, 'error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    // ────────────────────────────────────────────────────────────────
    // Nginx SSL Deployment
    // ────────────────────────────────────────────────────────────────

    protected function deploySslNginxConfig(Domain $domain, SslCertificate $cert): void
    {
        $certDir  = config('larapanel.paths.ssl_certs') . '/' . $domain->name;
        $certFile = "{$certDir}/fullchain.pem";
        $keyFile  = "{$certDir}/privkey.pem";

        if (app()->isProduction()) {
            // Generate and deploy SSL vhost config
            $config = $this->domains->generateNginxSslConfig($domain, $certFile, $keyFile);
            $sitesAvail   = config('larapanel.paths.nginx_sites');
            $sitesEnabled = config('larapanel.paths.nginx_enabled');

            file_put_contents("/tmp/lp_ssl_{$domain->name}", $config);
            $this->sudo->run(['cp', "/tmp/lp_ssl_{$domain->name}", "{$sitesAvail}/{$domain->name}"]);
            $this->sudo->run(['ln', '-sf', "{$sitesAvail}/{$domain->name}", "{$sitesEnabled}/{$domain->name}"]);
            $this->sudo->reloadNginx();
        }
    }

    protected function writeAndDeployCert(Domain $domain, string $cert, string $key, string $chain): void
    {
        if (!app()->isProduction()) return;

        $certDir = config('larapanel.paths.ssl_certs') . '/' . $domain->name;
        $this->sudo->run(['mkdir', '-p', $certDir]);
        file_put_contents("/tmp/lp_cert_{$domain->name}.pem",  $cert);
        file_put_contents("/tmp/lp_key_{$domain->name}.pem",   $key);
        file_put_contents("/tmp/lp_chain_{$domain->name}.pem", $chain);
        $this->sudo->run(['cp', "/tmp/lp_cert_{$domain->name}.pem",  "{$certDir}/fullchain.pem"]);
        $this->sudo->run(['cp', "/tmp/lp_key_{$domain->name}.pem",   "{$certDir}/privkey.pem"]);
        $this->sudo->run(['cp', "/tmp/lp_chain_{$domain->name}.pem", "{$certDir}/chain.pem"]);
        $this->sudo->run(['chown', '-R', 'www-data:www-data', $certDir]);
        $this->sudo->run(['chmod', '600', "{$certDir}/privkey.pem"]);
    }

    // ────────────────────────────────────────────────────────────────
    // Validation Helpers
    // ────────────────────────────────────────────────────────────────

    protected function validateCertificateKeyPair(string $cert, string $key): void
    {
        // Get public key from certificate
        $certResource = openssl_x509_read($cert);
        if (!$certResource) {
            throw new \RuntimeException('El certificado no es un PEM válido.');
        }

        $keyResource = openssl_pkey_get_private($key);
        if (!$keyResource) {
            throw new \RuntimeException('La llave privada no es un PEM válido.');
        }

        if (!openssl_x509_check_private_key($certResource, $keyResource)) {
            throw new \RuntimeException('La llave privada no corresponde al certificado.');
        }
    }

    protected function validateCertDomain(string $cert, string $domainName): void
    {
        $certData = openssl_x509_parse($cert);
        if (!$certData) return; // skip if parse fails

        $cn = $certData['subject']['CN'] ?? '';
        $san = $certData['extensions']['subjectAltName'] ?? '';

        $matches = str_contains($cn, $domainName)
            || str_contains($san, $domainName)
            || str_contains($san, '*.' . implode('.', array_slice(explode('.', $domainName), 1)));

        if (!$matches) {
            throw new \RuntimeException("El certificado no es válido para el dominio {$domainName}.");
        }
    }

    public function parseCertExpiry(string $certPem): ?\Illuminate\Support\Carbon
    {
        if (str_starts_with($certPem, '(dev-mode')) {
            return now()->addMonths(3);
        }
        $cert = @openssl_x509_read($certPem);
        if (!$cert) return null;

        $data = openssl_x509_parse($cert);
        if (!isset($data['validTo_time_t'])) return null;

        return \Illuminate\Support\Carbon::createFromTimestamp($data['validTo_time_t']);
    }

    public function getCertificateInfo(string $certPem): array
    {
        $cert = @openssl_x509_read($certPem);
        if (!$cert) return [];

        $data = openssl_x509_parse($cert);
        return [
            'subject'     => $data['subject'] ?? [],
            'issuer'      => $data['issuer'] ?? [],
            'valid_from'  => isset($data['validFrom_time_t']) ? \Carbon\Carbon::createFromTimestamp($data['validFrom_time_t'])->toDateString() : null,
            'valid_to'    => isset($data['validTo_time_t'])   ? \Carbon\Carbon::createFromTimestamp($data['validTo_time_t'])->toDateString() : null,
            'san'         => $data['extensions']['subjectAltName'] ?? '',
            'algorithm'   => $data['signatureTypeSN'] ?? '',
            'serial'      => $data['serialNumberHex'] ?? '',
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Development simulation
    // ────────────────────────────────────────────────────────────────

    protected function simulateCertificate(Domain $domain, SslCertificate $cert, string $provider): void
    {
        sleep(1); // simulate async operation

        $cert->update([
            'status'      => 'active',
            'certificate' => "(dev-mode: simulated {$provider} certificate for {$domain->name})",
            'private_key' => Crypt::encryptString("(dev-mode-private-key)"),
            'chain'       => "(dev-mode: CA chain)",
            'issued_at'   => now(),
            'expires_at'  => now()->addMonths(3),
        ]);

        $domain->update([
            'ssl_enabled'    => true,
            'ssl_expires_at' => now()->addMonths(3),
            'ssl_provider'   => $provider,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Utility
    // ────────────────────────────────────────────────────────────────

    public function isAcmeShInstalled(): bool
    {
        return file_exists($this->acmeSh);
    }

    public function isCertbotInstalled(): bool
    {
        return (bool) shell_exec('which certbot 2>/dev/null');
    }

    public function getDomainsWithoutSsl(): \Illuminate\Database\Eloquent\Collection
    {
        return Domain::where('ssl_enabled', false)
            ->where('status', 'active')
            ->where('user_id', auth()->id())
            ->get();
    }
}
