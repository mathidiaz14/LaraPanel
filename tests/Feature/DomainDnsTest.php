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

    public function test_creating_subdomain_creates_dns_a_record_in_parent_zone()
    {
        config()->set('larapanel.server.public_ip', '10.20.30.40');

        $main = $this->domainService->create($this->user, [
            'name'          => 'recibos.host.com',
            'type'          => 'main',
            'parent_domain' => null,
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'document_root' => '/var/www/recibos.host.com/public_html',
        ]);

        $zone = DnsZone::where('domain_id', $main->id)->first();
        $this->assertNotNull($zone);

        $sub = $this->domainService->create($this->user, [
            'name'          => 'app.recibos.host.com',
            'type'          => 'subdomain',
            'parent_domain' => $main->id,
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'document_root' => '/var/www/app.recibos.host.com/public_html',
        ]);

        $this->assertDatabaseHas('domains', [
            'id'   => $sub->id,
            'name' => 'app.recibos.host.com',
            'type' => 'subdomain',
        ]);

        // The subdomain should get an A record inside the parent's zone.
        $this->assertDatabaseHas('dns_records', [
            'dns_zone_id' => $zone->id,
            'name'        => 'app',
            'type'        => 'A',
            'content'     => '10.20.30.40',
        ]);
    }

    public function test_creating_subdomain_does_not_duplicate_existing_a_record()
    {
        config()->set('larapanel.server.public_ip', '10.20.30.40');

        $main = $this->domainService->create($this->user, [
            'name'          => 'webmail.dup.com',
            'type'          => 'main',
            'parent_domain' => null,
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'document_root' => '/var/www/webmail.dup.com/public_html',
        ]);

        $zone = DnsZone::where('domain_id', $main->id)->first();
        $this->assertNotNull($zone);

        // The webmail subdomain is auto-created with a base record already present.
        $sub = $this->domainService->create($this->user, [
            'name'          => 'api.webmail.dup.com',
            'type'          => 'subdomain',
            'parent_domain' => $main->id,
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'document_root' => '/var/www/api.webmail.dup.com/public_html',
        ]);

        $this->assertNotNull($sub);

        // Only one A record for 'api' should exist in the zone.
        $this->assertSame(1, DnsZone::find($zone->id)
            ->records()
            ->where('name', 'api')
            ->where('type', 'A')
            ->count());
    }
}
