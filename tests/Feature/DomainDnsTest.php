<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Domain;
use App\Models\DnsZone;
use App\Services\DomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainDnsTest extends TestCase
{
    use RefreshDatabase;

    protected DomainService $domainService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->domainService = app(DomainService::class);
        $this->user = User::factory()->create();
    }

    public function test_creating_main_domain_automatically_creates_dns_zone()
    {
        $domainName = 'maindomain.com';
        
        $domain = $this->domainService->create($this->user, [
            'name'          => $domainName,
            'type'          => 'main',
            'parent_domain' => null,
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'document_root' => '/var/www/maindomain.com/public_html',
        ]);

        $this->assertDatabaseHas('domains', [
            'id'   => $domain->id,
            'name' => $domainName,
            'type' => 'main',
        ]);

        $this->assertDatabaseHas('dns_zones', [
            'domain_id' => $domain->id,
            'name'      => $domainName,
        ]);

        // It should have seeded default records (A @, A www, A webmail, A mail, MX @, SPF TXT, DMARC TXT)
        $zone = DnsZone::where('domain_id', $domain->id)->first();
        $this->assertNotNull($zone);

        foreach ([
            ['@', 'A'],
            ['www', 'A'],
            ['webmail', 'A'],
            ['mail', 'A'],
            ['@', 'MX'],
            ['@', 'TXT'],
            ['_dmarc', 'TXT'],
        ] as [$name, $type]) {
            $this->assertDatabaseHas('dns_records', [
                'dns_zone_id' => $zone->id,
                'name'        => $name,
                'type'        => $type,
            ]);
        }
    }

    public function test_creating_main_domain_automatically_publishes_dkim_record()
    {
        $domainName = 'dkimdomain.com';

        $domain = $this->domainService->create($this->user, [
            'name'          => $domainName,
            'type'          => 'main',
            'parent_domain' => null,
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'document_root' => '/var/www/dkimdomain.com/public_html',
        ]);

        $this->assertDatabaseHas('dkim_keys', [
            'domain_id' => $domain->id,
            'selector'  => 'mail',
            'is_active' => true,
        ]);

        $zone = DnsZone::where('domain_id', $domain->id)->first();
        $this->assertNotNull($zone);

        $this->assertDatabaseHas('dns_records', [
            'dns_zone_id' => $zone->id,
            'name'        => 'mail._domainkey',
            'type'        => 'TXT',
        ]);
    }

    public function test_creating_subdomain_does_not_create_dns_zone()
    {
        $domainName = 'sub.maindomain.com';
        
        $domain = $this->domainService->create($this->user, [
            'name'          => $domainName,
            'type'          => 'subdomain',
            'parent_domain' => 'maindomain.com',
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'document_root' => '/var/www/sub.maindomain.com/public_html',
        ]);

        $this->assertDatabaseHas('domains', [
            'id'   => $domain->id,
            'name' => $domainName,
            'type' => 'subdomain',
        ]);

        $this->assertDatabaseMissing('dns_zones', [
            'domain_id' => $domain->id,
            'name'      => $domainName,
        ]);
    }
}
