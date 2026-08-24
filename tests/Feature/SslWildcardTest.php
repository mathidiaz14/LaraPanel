<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\SslCertificate;
use App\Models\User;
use App\Services\SslService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SslWildcardTest extends TestCase
{
    use RefreshDatabase;

    protected SslService $sslService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sslService = app(SslService::class);
        $this->user = User::factory()->create();
    }

    protected function createDomain(string $name, string $type = 'main', ?int $parentId = null): Domain
    {
        return Domain::create([
            'user_id'       => $this->user->id,
            'name'          => $name,
            'type'          => $type,
            'parent_domain' => $parentId,
            'document_root' => '/var/www/' . $name . '/public_html',
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'status'        => 'active',
            'is_active'     => true,
        ]);
    }

    public function test_wildcard_issue_covers_all_active_subdomains()
    {
        $main    = $this->createDomain('example.com');
        $webmail = $this->createDomain('webmail.example.com', 'subdomain', $main->id);
        $api     = $this->createDomain('api.example.com', 'subdomain', $main->id);

        $cert = $this->sslService->issueLetsEncrypt(domain: $main, isWildcard: true);

        foreach ([$webmail, $api] as $sub) {
            $sub->refresh();

            $this->assertTrue($sub->ssl_enabled);
            $this->assertSame('letsencrypt', $sub->ssl_provider);
            $this->assertNotNull($sub->ssl_expires_at);

            $this->assertDatabaseHas('ssl_certificates', [
                'domain_id'  => $sub->id,
                'provider'   => 'letsencrypt',
                'status'     => 'active',
                'auto_renew' => false,
            ]);

            $this->assertSame(['*.' . $main->name], $sub->sslCertificate->san_domains);
        }

        // El dominio padre mantiene la auto-renovación del wildcard
        $this->assertDatabaseHas('ssl_certificates', [
            'domain_id'  => $main->id,
            'provider'   => 'letsencrypt',
            'status'     => 'active',
            'auto_renew' => true,
        ]);

        $this->assertEquals($cert->expires_at?->toDateString(), $webmail->refresh()->ssl_expires_at?->toDateString());
    }

    public function test_wildcard_propagation_skips_custom_certificates()
    {
        $main = $this->createDomain('protected.com');
        $shop = $this->createDomain('shop.protected.com', 'subdomain', $main->id);

        SslCertificate::create([
            'domain_id'   => $shop->id,
            'provider'    => 'custom',
            'status'      => 'active',
            'certificate' => '(custom)',
            'private_key' => encrypt('(custom-key)'),
            'issued_at'   => now(),
            'expires_at'  => now()->addYear(),
            'auto_renew'  => false,
            'san_domains' => ['shop.protected.com'],
        ]);

        $this->sslService->issueLetsEncrypt(domain: $main, isWildcard: true);

        $shop->refresh();
        $this->assertDatabaseHas('ssl_certificates', [
            'domain_id' => $shop->id,
            'provider'  => 'custom',
        ]);
        $this->assertFalse($shop->ssl_enabled || $shop->ssl_provider === 'letsencrypt');
    }

    public function test_revoking_wildcard_reverts_covered_subdomains()
    {
        $main    = $this->createDomain('revoke.com');
        $webmail = $this->createDomain('webmail.revoke.com', 'subdomain', $main->id);

        $this->sslService->issueLetsEncrypt(domain: $main, isWildcard: true);
        $this->assertTrue($webmail->refresh()->ssl_enabled);

        $this->sslService->revoke($main);

        $webmail->refresh();
        $this->assertFalse($webmail->ssl_enabled);
        $this->assertNull($webmail->ssl_provider);
        $this->assertDatabaseHas('ssl_certificates', [
            'domain_id' => $webmail->id,
            'status'    => 'revoked',
        ]);
        $this->assertDatabaseMissing('ssl_certificates', [
            'domain_id' => $webmail->id,
            'status'    => 'active',
        ]);
    }
}
