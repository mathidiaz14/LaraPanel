<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\SslCertificate;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use App\Shell\ShellExecutor;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
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
        protected ShellExecutor   $shell,
        protected DomainService   $domains,
    ) {}

    // ────────────────────────────────────────────────────────────────
    // Let's Encrypt — Issue Certificate
    // ────────────────────────────────────────────────────────────────

    /**
     * Issue a Let's Encrypt certificate using the webroot challenge.
     * Works even with Nginx running (no downtime).
     *
     * When $viaDns is true the DNS-01 challenge is used (via the local
     * PowerDNS API through the acme.sh dns_pdns plugin). DNS-01 is required
     * for wildcard certs, but can also be forced for regular domains that are
     * not (yet) pointing their HTTP traffic to this server.
     *
     * @param  Domain  $domain
     * @param  array   $sanDomains  Additional SANs e.g. ['www.example.com']
     * @param  bool    $includeWww  Auto-add www. variant
     * @param  bool    $viaDns      Force DNS-01 (PowerDNS) challenge
     */
    public function issueLetsEncrypt(Domain $domain, array $sanDomains = [], bool $includeWww = true, bool $isWildcard = false, bool $viaDns = false): SslCertificate
    {
        // Wildcards always require the DNS-01 challenge (PowerDNS).
        $viaDns = $viaDns || $isWildcard;

        // Build the full SAN list
        $allDomains = [$domain->name];
        if ($isWildcard) {
            $allDomains[] = '*.' . $domain->name;
        } else {
            $wwwDomain = 'www.' . $domain->name;
            if ($includeWww && !str_starts_with($domain->name, 'www.') && $this->resolvesInDns($wwwDomain)) {
                $allDomains[] = $wwwDomain;
            }
        }
        $allDomains = array_unique(array_merge($allDomains, $sanDomains));

        // Create or update the DB record in pending state
        $cert = SslCertificate::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'provider'       => 'letsencrypt',
                'status'         => 'pending',
                'auto_renew'     => true,
                'challenge_type' => $viaDns ? 'dns_pdns' : 'webroot',
                'san_domains'    => $allDomains,
                'last_error'     => null,
            ]
        );

        AuditLog::record('ssl.letsencrypt.started', $domain->name, ['domains' => $allDomains]);

        try {
            if (app()->isProduction()) {
                $this->runAcmeSh($domain, $allDomains, $cert, $isWildcard, $viaDns);
            } else {
                // Development mode: simulate certificate issuance
                $this->simulateCertificate($domain, $cert, 'letsencrypt');
            }

            // Deploy SSL nginx config
            $this->deploySslNginxConfig($domain, $cert);

            AuditLog::record('ssl.letsencrypt.issued', $domain->name, [
                'expires_at' => $cert->fresh()->expires_at?->toIso8601String(),
            ]);

            // Wildcard: cubrir automáticamente todos los subdominios activos con este certificado
            if ($isWildcard) {
                try {
                    $this->propagateWildcardToSubdomains($domain, $cert->fresh());
                } catch (\Throwable $e) {
                    Log::error("Failed to propagate wildcard SSL to subdomains of {$domain->name}: " . $e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $cert->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            AuditLog::record('ssl.letsencrypt.failed', $domain->name, ['error' => $e->getMessage()], severity: 'critical');
            throw $e;
        }

        return $cert->fresh();
    }

    /**
     * Check whether a hostname resolves in DNS (A/AAAA/CNAME).
     */
    protected function resolvesInDns(string $hostname): bool
    {
        return @checkdnsrr($hostname, 'A') || @checkdnsrr($hostname, 'AAAA') || @checkdnsrr($hostname, 'CNAME');
    }

    // ────────────────────────────────────────────────────────────────
    // Wildcard — Cover all subdomains
    // ────────────────────────────────────────────────────────────────

    /**
     * Apply the wildcard certificate of $domain to all its active subdomains.
     *
     * Each subdomain gets an SslCertificate copy (auto_renew off, renewed by
     * the parent wildcard), SSL enabled on the domain row and its Nginx vhost
     * redeployed pointing to the SAME cert files as the parent domain.
     *
     * @return int Number of subdomains covered.
     */
    public function propagateWildcardToSubdomains(Domain $domain, SslCertificate $cert): int
    {
        $wildcardSan   = '*.' . $domain->name;
        $parentCertDir = config('larapanel.paths.ssl_certs') . '/' . $domain->name;

        $subdomains = Domain::where('user_id', $domain->user_id)
            ->where('id', '!=', $domain->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Domain $d) => str_ends_with($d->name, '.' . $domain->name));

        $covered = 0;

        foreach ($subdomains as $sub) {
            // No tocar certificados custom instalados manualmente
            if ($sub->sslCertificate?->provider === 'custom') {
                continue;
            }

            SslCertificate::updateOrCreate(
                ['domain_id' => $sub->id],
                [
                    'provider'        => 'letsencrypt',
                    'challenge_type'  => $cert->challenge_type,
                    'status'          => 'active',
                    'certificate'     => $cert->certificate,
                    'private_key'     => $cert->private_key,
                    'chain'           => $cert->chain,
                    'issued_at'       => $cert->issued_at,
                    'expires_at'      => $cert->expires_at,
                    'last_renewed_at' => $cert->last_renewed_at,
                    'auto_renew'      => false,
                    'san_domains'     => [$wildcardSan],
                    'last_error'      => null,
                ]
            );

            $sub->update([
                'ssl_enabled'    => true,
                'ssl_expires_at' => $cert->expires_at,
                'ssl_provider'   => 'letsencrypt',
            ]);

            $this->deploySslNginxConfig($sub, $cert, certDirOverride: $parentCertDir, reload: false);

            AuditLog::record('ssl.wildcard.propagated', $sub->name, ['wildcard' => $wildcardSan]);

            $covered++;
        }

        if ($covered > 0 && app()->isProduction()) {
            $this->sudo->reloadNginx();
        }

        return $covered;
    }

    /**
     * Run acme.sh to issue the certificate (production only).
     *
     * DNS-01 (PowerDNS / dns_pdns) is used for wildcard certificates and for
     * regular certificates issued with the DNS challenge.
     */
    protected function runAcmeSh(Domain $domain, array $allDomains, SslCertificate $cert, bool $isWildcard = false, bool $viaDns = false): void
    {
        if ($isWildcard || $viaDns) {
            // Extracción y sanitización de credenciales de PowerDNS local para acme.sh
            $pdnsUrl = config('larapanel.powerdns.api_url', 'http://127.0.0.1:8053/api/v1');
            $basePdnsUrl = preg_replace('/\/api\/v1\/?$/i', '', $pdnsUrl);
            $pdnsServer = config('larapanel.powerdns.server', 'localhost');
            $pdnsToken = config('larapanel.powerdns.api_key', 'larapanel_pdns_secret');

            $env = [
                'PDNS_Url'      => $basePdnsUrl,
                'PDNS_ServerId' => $pdnsServer,
                'PDNS_Token'    => $pdnsToken,
            ];

            // Issue via acme.sh dns_pdns method
            $result = $this->sudo->withEnv($env)->withTimeout(900)->run([
                $this->acmeSh, '--issue',
                '--dns', 'dns_pdns',
                ...array_merge(...array_map(fn($d) => ['-d', $d], $allDomains)),
                '--server', 'letsencrypt',
                '--force',
            ], checkExit: false);
        } else {
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
        }

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

        // Si era un wildcard, revierte también los subdominios que usaban este cert
        $wildcardSan = '*.' . $domain->name;
        Domain::where('user_id', $domain->user_id)
            ->where('id', '!=', $domain->id)
            ->get()
            ->filter(fn (Domain $d) => str_ends_with($d->name, '.' . $domain->name))
            ->each(function (Domain $child) use ($wildcardSan, $domain) {
                $childCert = $child->sslCertificate;
                if ($childCert && in_array($wildcardSan, $childCert->san_domains ?? [], true)) {
                    $childCert->update(['status' => 'revoked']);
                    $child->update([
                        'ssl_enabled'    => false,
                        'ssl_expires_at' => null,
                        'ssl_provider'   => null,
                    ]);
                    $this->domains->deployConfigs($child);
                    AuditLog::record('ssl.revoked', $child->name, ['wildcard_parent' => $domain->name]);
                }
            });

        AuditLog::record('ssl.revoked', $domain->name);
    }

    // ────────────────────────────────────────────────────────────────
    // Auto-Renewal (called by Laravel Scheduler)
    // ────────────────────────────────────────────────────────────────

    /**
     * Renew a single Let's Encrypt certificate, reusing the challenge method
     * (and SAN list) originally used so renewals actually succeed.
     *
     * For wildcard certs whose authoritative DNS is NOT the local PowerDNS
     * (e.g. Cloudflare-hosted apex domains), DNS-01 can never complete, so it
     * transparently falls back to individual HTTP-01 certs for the main domain
     * and every active subdomain.
     */
    public function renewCertificate(Domain $domain): SslCertificate
    {
        $cert = $domain->sslCertificate;
        if (!$cert || $cert->provider !== 'letsencrypt') {
            throw new \RuntimeException("El dominio {$domain->name} no tiene un certificado Let's Encrypt renovable.");
        }

        [$isWildcard, $extraSans, $includeWww, $viaDns] = $this->renewalParamsFor($cert);

        if ($isWildcard && $viaDns && !$this->isPowerDnsAuthoritative($domain)) {
            return $this->issueWildcardFallback($domain);
        }

        return $this->issueLetsEncrypt(
            domain:     $domain,
            sanDomains: $extraSans,
            includeWww: $includeWww,
            isWildcard: $isWildcard,
            viaDns:     $viaDns,
        );
    }

    /**
     * Renew all Let's Encrypt certs that need it:
     *  - active certs expiring within the next 30 days
     *  - certs in failed / expired status (retried automatically)
     *
     * @param  bool  $force  When true, renew every auto-renewable Let's Encrypt cert
     *                       regardless of expiry (used by the `--force` flag).
     */
    public function renewAll(bool $force = false): array
    {
        $results = ['renewed' => [], 'failed' => [], 'skipped' => []];

        $expiring = SslCertificate::where('provider', 'letsencrypt')
            ->where('auto_renew', true)
            ->whereIn('status', ['active', 'failed', 'expired'])
            ->with('domain');

        if (!$force) {
            $expiring->where(function ($q) {
                $q->where('expires_at', '<=', now()->addDays(30))
                  ->orWhereIn('status', ['failed', 'expired']);
            });
        }

        foreach ($expiring->get() as $cert) {
            if (!$cert->domain) {
                $results['skipped'][] = $cert->id;
                continue;
            }
            try {
                $this->renewCertificate($cert->domain);
                $cert->update(['last_renewed_at' => now()]);
                $results['renewed'][] = $cert->domain->name;
            } catch (\Throwable $e) {
                $results['failed'][] = ['domain' => $cert->domain->name, 'error' => $e->getMessage()];
                Log::error('SSL auto-renew failed', ['domain' => $cert->domain->name, 'error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Rebuild the issuance parameters for a certificate from its stored SANs.
     *
     * @return array{0: bool, 1: array, 2: bool, 3: bool} [isWildcard, extraSans, includeWww, viaDns]
     */
    protected function renewalParamsFor(SslCertificate $cert): array
    {
        $sans       = $cert->san_domains ?? [];
        $domainName = $cert->domain?->name;

        $isWildcard = collect($sans)->contains(fn ($san) => str_starts_with((string) $san, '*.'));

        $extraSans = array_values(array_filter($sans, function ($d) use ($domainName) {
            return $d !== $domainName && $d !== 'www.' . $domainName && !str_starts_with((string) $d, '*.');
        }));

        $includeWww = in_array('www.' . $domainName, $sans, true);

        $viaDns = $isWildcard || $cert->usesDnsChallenge();

        return [$isWildcard, $extraSans, $includeWww, $viaDns];
    }

    /**
     * Issue individual (non-wildcard) HTTP-01 certificates for the main domain
     * and every active subdomain. Used when a wildcard cannot be validated via
     * DNS-01 because the domain's authoritative DNS is not the local PowerDNS.
     */
    protected function issueWildcardFallback(Domain $domain): SslCertificate
    {
        Log::info('SSL auto-renew: DNS autoritativo no es PowerDNS local; emitiendo certificados individuales HTTP-01', ['domain' => $domain->name]);

        $parent = $this->issueLetsEncrypt(domain: $domain, includeWww: true, isWildcard: false);

        $subdomains = Domain::where('user_id', $domain->user_id)
            ->where('id', '!=', $domain->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Domain $d) => str_ends_with($d->name, '.' . $domain->name))
            ->reject(fn (Domain $d) => $d->sslCertificate?->provider === 'custom');

        foreach ($subdomains as $sub) {
            try {
                $this->issueLetsEncrypt(domain: $sub, includeWww: true, isWildcard: false);
            } catch (\Throwable $e) {
                Log::error("SSL wildcard fallback failed for subdomain {$sub->name}: " . $e->getMessage());
            }
        }

        // Los subdominios que seguían cubiertos por el wildcard (copias con
        // auto_renew=false) deben quedarse con auto-renovación propia para que
        // el scheduler los siga reintentando hasta tener su certificado.
        Domain::where('user_id', $domain->user_id)
            ->where('id', '!=', $domain->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Domain $d) => str_ends_with($d->name, '.' . $domain->name)
                && $d->sslCertificate
                && $d->sslCertificate->provider === 'letsencrypt'
                && in_array('*.' . $domain->name, $d->sslCertificate->san_domains ?? [], true))
            ->each(fn (Domain $sub) => $sub->sslCertificate->update([
                'auto_renew'      => true,
                'challenge_type'  => 'webroot',
                'san_domains'     => [$sub->name, 'www.' . $sub->name],
            ]));

        return $parent;
    }

    /**
     * Whether the local PowerDNS instance is the authoritative nameserver for
     * the given domain. DNS-01 via dns_pdns only works when that holds.
     *
     * We compare the public SOA serial with the zone serial stored in the
     * local PowerDNS: when the domain's delegation ends up served by this
     * same PowerDNS (even through a "misconfigured" delegation), the serials
     * match — when a real registrar/Cloudflare serves the zone, they don't.
     */
    protected function isPowerDnsAuthoritative(Domain $domain): bool
    {
        if (!config('larapanel.powerdns.enabled', true)) {
            return false;
        }

        try {
            $token = config('larapanel.powerdns.api_key');
            if (!$token) {
                return false;
            }

            $base   = rtrim((string) config('larapanel.powerdns.api_url', 'http://127.0.0.1:8053/api/v1'), '/');
            $server = config('larapanel.powerdns.server', 'localhost');
            $zone   = Http::withToken($token)
                ->acceptJson()
                ->timeout(5)
                ->get("{$base}/servers/" . urlencode($server) . '/zones/' . urlencode(rtrim($domain->name, '.') . '.'))
                ->json();
            if (!is_array($zone) || !isset($zone['serial'])) {
                return false;
            }
            $localSerial = (int) $zone['serial'];

            $soa = @dns_get_record($domain->name, DNS_SOA);
            if (!$soa) {
                return false;
            }
            $publicSerial = (int) ($soa[0]['serial'] ?? 0);

            return $localSerial > 0 && $publicSerial === $localSerial;
        } catch (\Throwable) {
            return false;
        }
    }

    // ────────────────────────────────────────────────────────────────
    // Nginx SSL Deployment
    // ────────────────────────────────────────────────────────────────

    protected function deploySslNginxConfig(Domain $domain, SslCertificate $cert, ?string $certDirOverride = null, bool $reload = true): void
    {
        $certDir  = $certDirOverride ?? (config('larapanel.paths.ssl_certs') . '/' . $domain->name);
        $certFile = "{$certDir}/fullchain.pem";
        $keyFile  = "{$certDir}/privkey.pem";

        if (app()->isProduction()) {
            // Generate and deploy SSL vhost config
            $config = $this->domains->generateNginxSslConfig($domain, $certFile, $keyFile);
            $sitesAvail   = config('larapanel.paths.nginx_sites');
            $sitesEnabled = config('larapanel.paths.nginx_enabled');

            $tmp = tempnam(sys_get_temp_dir(), 'lp_ssl_');
            file_put_contents($tmp, $config);
            $this->sudo->run(['cp', $tmp, "{$sitesAvail}/{$domain->name}"]);
            @unlink($tmp);
            $this->sudo->run(['ln', '-sf', "{$sitesAvail}/{$domain->name}", "{$sitesEnabled}/{$domain->name}"]);

            if ($reload) {
                $this->sudo->reloadNginx();
            }
        }
    }

    protected function writeAndDeployCert(Domain $domain, string $cert, string $key, string $chain): void
    {
        if (!app()->isProduction()) return;

        $certDir = config('larapanel.paths.ssl_certs') . '/' . $domain->name;
        $this->sudo->run(['mkdir', '-p', $certDir]);
        $tmpCert  = tempnam(sys_get_temp_dir(), 'lp_cert_');
        $tmpKey   = tempnam(sys_get_temp_dir(), 'lp_key_');
        $tmpChain = tempnam(sys_get_temp_dir(), 'lp_chain_');
        file_put_contents($tmpCert,  $cert);
        file_put_contents($tmpKey,   $key);
        file_put_contents($tmpChain, $chain);
        $this->sudo->run(['cp', $tmpCert,  "{$certDir}/fullchain.pem"]);
        $this->sudo->run(['cp', $tmpKey,   "{$certDir}/privkey.pem"]);
        $this->sudo->run(['cp', $tmpChain, "{$certDir}/chain.pem"]);
        @unlink($tmpCert);
        @unlink($tmpKey);
        @unlink($tmpChain);
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
        return $this->shell->run(['which', 'certbot'], false)->successful();
    }

    public function getDomainsWithoutSsl(): \Illuminate\Database\Eloquent\Collection
    {
        return Domain::where('ssl_enabled', false)
            ->where('status', 'active')
            ->where('user_id', auth()->id())
            ->get();
    }
}
