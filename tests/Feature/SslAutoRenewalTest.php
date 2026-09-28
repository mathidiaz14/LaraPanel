<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\SslCertificate;
use App\Models\User;
use App\Services\SslService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SslAutoRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected SslService $sslService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sslService = app(SslService::class);
        $this->user = User::factory()->create();

        // Hermetic: no tocar el PowerDNS real ni resolver DNS del host durante el test.
        config(['larapanel.powerdns.enabled' => false]);
        config(['larapanel.powerdns.api_key' => null]);
    }

    protected function createDomain(string $name, string $type = 'main', ?int $parentId = null): Domain
    {
        return Domain::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => $type,
            'parent_domain' => $parentId,
            'document_root' => '/var/www/'.$name.'/public_html',
            'php_version' => '8.3',
            'webserver' => 'nginx',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    protected function createCert(Domain $domain, array $overrides = []): SslCertificate
    {
        return SslCertificate::create(array_merge([
            'domain_id' => $domain->id,
            'provider' => 'letsencrypt',
            'challenge_type' => 'webroot',
            'status' => 'active',
            'certificate' => '(dev-mode: simulated letsencrypt certificate)',
            'private_key' => encrypt('(dev-mode-private-key)'),
            'chain' => '(dev-mode: CA chain)',
            'issued_at' => now()->subMonths(2),
            'expires_at' => now()->addDays(10),
            'auto_renew' => true,
            'san_domains' => [$domain->name, 'www.'.$domain->name],
        ], $overrides));
    }

    public function test_challenge_type_is_persisted_on_issue(): void
    {
        $domain = $this->createDomain('example.com');

        $this->sslService->issueLetsEncrypt(domain: $domain, isWildcard: false, viaDns: false);
        $this->assertSame('webroot', $domain->fresh()->sslCertificate->challenge_type);

        $this->sslService->issueLetsEncrypt(domain: $domain, isWildcard: false, viaDns: true);
        $this->assertSame('dns_pdns', $domain->fresh()->sslCertificate->challenge_type);

        $this->sslService->issueLetsEncrypt(domain: $domain, isWildcard: true);
        $this->assertSame('dns_pdns', $domain->fresh()->sslCertificate->challenge_type);
    }

    public function test_renew_all_retries_failed_certificates(): void
    {
        $domain = $this->createDomain('failed.com');
        $this->createCert($domain, [
            'status' => 'failed',
            'expires_at' => now()->subDay(),
            'last_error' => 'previous failure',
            'san_domains' => ['failed.com', 'www.failed.com'],
        ]);

        $results = $this->sslService->renewAll();

        $this->assertContains('failed.com', $results['renewed']);
        $this->assertEmpty($results['failed']);
        $this->assertDatabaseHas('ssl_certificates', [
            'domain_id' => $domain->id,
            'status' => 'active',
        ]);
    }

    public function test_renew_all_skips_certs_not_expiring_soon(): void
    {
        $far = $this->createDomain('far.com');
        $this->createCert($far, ['expires_at' => now()->addMonths(2), 'status' => 'active']);

        $soon = $this->createDomain('soon.com');
        $this->createCert($soon, ['expires_at' => now()->addDays(10), 'status' => 'active']);

        $results = $this->sslService->renewAll();

        $this->assertContains('soon.com', $results['renewed']);
        $this->assertNotContains('far.com', $results['renewed']);
    }

    public function test_renew_certificate_honors_stored_dns_challenge(): void
    {
        $domain = $this->createDomain('dns.com');
        $this->createCert($domain, [
            'challenge_type' => 'dns_pdns',
            'status' => 'active',
            'expires_at' => now()->addDays(15),
            'san_domains' => ['dns.com', 'www.dns.com'],
        ]);

        $this->sslService->renewCertificate($domain);

        $this->assertSame('dns_pdns', $domain->fresh()->sslCertificate->challenge_type);
        $this->assertSame('active', $domain->fresh()->sslCertificate->status);
    }

    public function test_wildcard_without_authoritative_dns_falls_back_to_individual_certs(): void
    {
        $main = $this->createDomain('example.com');
        $webmail = $this->createDomain('webmail.example.com', 'subdomain', $main->id);
        $api = $this->createDomain('api.example.com', 'subdomain', $main->id);

        // Wildcard cert covering the apex (as produced by a previous DNS-01 issue)
        $this->createCert($main, [
            'challenge_type' => 'dns_pdns',
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'san_domains' => ['example.com', '*.example.com'],
        ]);
        // Subdomains previously "covered" by the wildcard (copies)
        foreach ([$webmail, $api] as $sub) {
            $this->createCert($sub, [
                'challenge_type' => 'dns_pdns',
                'auto_renew' => false,
                'expires_at' => now()->addDays(10),
                'san_domains' => ['*.example.com'],
            ]);
        }

        $results = $this->sslService->renewAll();

        // The parent must be downgraded to a non-wildcard HTTP-01 cert
        $this->assertSame('webroot', $main->fresh()->sslCertificate->challenge_type);
        $this->assertNotContains('*.example.com', $main->fresh()->sslCertificate->san_domains);
        $this->assertContains('example.com', $main->fresh()->sslCertificate->san_domains);
        $this->assertContains('www.example.com', $main->fresh()->sslCertificate->san_domains);

        // Every active subdomain gets its own independent cert
        foreach ([$webmail, $api] as $sub) {
            $subCert = $sub->fresh()->sslCertificate;
            $this->assertSame('active', $subCert->status);
            $this->assertSame('webroot', $subCert->challenge_type);
            $this->assertTrue($subCert->auto_renew);
            $this->assertContains($sub->name, $subCert->san_domains);
        }

        $this->assertNotEmpty($results['renewed']);
    }

    public function test_force_renew_all_renews_every_letsencrypt_cert(): void
    {
        $far = $this->createDomain('force-far.com');
        $this->createCert($far, ['expires_at' => now()->addMonths(2), 'status' => 'active']);

        $results = $this->sslService->renewAll(force: true);

        $this->assertContains('force-far.com', $results['renewed']);
    }
}
