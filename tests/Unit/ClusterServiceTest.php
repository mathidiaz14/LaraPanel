<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\User;
use App\Services\ClusterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClusterServiceTest extends TestCase
{
    use RefreshDatabase;

    private function domain(): Domain
    {
        $user = User::factory()->create(['role' => 'client']);

        return Domain::create([
            'user_id'       => $user->id,
            'name'          => 'example.com',
            'type'          => 'main',
            'document_root' => '/var/www/example.com/public_html',
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'status'        => 'active',
        ]);
    }

    public function test_add_and_remove_members_persist_in_config(): void
    {
        $domain = $this->domain();
        $service = app(ClusterService::class);

        $service->addMember($domain, '10.0.0.11:8080');
        $service->addMember($domain, '10.0.0.12:8080');

        $this->assertSame(
            ['10.0.0.11:8080', '10.0.0.12:8080'],
            array_map(fn ($m) => $m['address'], $service->members($domain)),
        );

        $service->removeMember($domain, '10.0.0.11:8080');

        $this->assertSame(
            ['10.0.0.12:8080'],
            array_map(fn ($m) => $m['address'], $service->members($domain)),
        );
    }

    public function test_duplicate_member_throws(): void
    {
        $domain = $this->domain();
        $service = app(ClusterService::class);

        $service->addMember($domain, '10.0.0.11:8080');

        $this->expectException(\InvalidArgumentException::class);
        $service->addMember($domain, '10.0.0.11:8080');
    }

    public function test_invalid_address_format_rejected(): void
    {
        $domain = $this->domain();
        $service = app(ClusterService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Formato inválido');
        $service->addMember($domain, 'not-an-address');
    }

    public function test_staging_cannot_be_enabled_without_members(): void
    {
        $domain = $this->domain();
        $service = app(ClusterService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No hay nodos');
        $service->setStaging($domain, true);
    }

    public function test_enabling_staging_flips_status_and_removing_last_member_disables_it(): void
    {
        $domain = $this->domain();
        $service = app(ClusterService::class);

        $service->addMember($domain, '10.0.0.11:8080');
        $service->setStaging($domain, true);

        $this->assertTrue($domain->fresh()->onStaging());

        $service->removeMember($domain, '10.0.0.11:8080');

        $fresh = $domain->fresh();
        $this->assertFalse($fresh->onStaging());
        $this->assertSame([], $fresh->getStagingUpstreams());
    }

    public function test_check_rejects_malformed_address(): void
    {
        $service = app(ClusterService::class);

        $this->assertFalse($service->check(''),
            'Un address vacío nunca debería considerarse "healthy".');
        $this->assertFalse($service->check('nope'));
    }
}