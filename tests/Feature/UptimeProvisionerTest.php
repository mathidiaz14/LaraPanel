<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\UptimeMonitor;
use App\Models\User;
use App\Services\DockerService;
use App\Services\UptimeProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UptimeProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function provisioner(): UptimeProvisioner
    {
        $docker = Mockery::mock(DockerService::class);
        $docker->shouldReceive('listContainers')->andReturn([]);

        return new UptimeProvisioner($docker);
    }

    protected function proxyDomain(User $user): Domain
    {
        return Domain::create([
            'user_id' => $user->id,
            'name' => 'ws.example.com',
            'type' => 'proxy',
            'document_root' => '/var/www/apps/ws',
            'php_version' => '8.3',
            'webserver' => 'nginx',
            'status' => 'active',
            'is_active' => true,
            'config' => ['proxy_port' => 4433],
        ]);
    }

    public function test_proxy_domains_are_not_enrolled_as_http_monitors(): void
    {
        $user = User::factory()->create();
        $domain = $this->proxyDomain($user);

        $this->assertNull($this->provisioner()->enrollDomain($domain));
        $this->assertDatabaseCount('uptime_monitors', 0);
    }

    public function test_stale_http_monitor_for_proxy_domain_is_pruned(): void
    {
        $user = User::factory()->create();
        $domain = $this->proxyDomain($user);
        $monitor = UptimeMonitor::create([
            'user_id' => $user->id,
            'name' => $domain->name,
            'type' => 'http',
            'target' => 'https://' . $domain->name,
            'interval_minutes' => 5,
            'status' => 'down',
            'source' => 'auto',
        ]);

        $this->assertSame(1, $this->provisioner()->pruneStale($user));
        $this->assertDatabaseMissing('uptime_monitors', ['id' => $monitor->id]);
    }
}
