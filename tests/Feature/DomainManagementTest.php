<?php

namespace Tests\Feature;

use App\Livewire\Domains\DomainIndex;
use App\Models\Domain;
use App\Models\User;
use App\Services\DomainService;
use App\Services\SslService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class DomainManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(): User
    {
        return User::factory()->create();
    }

    protected function createDomain(User $user, string $name, array $overrides = []): Domain
    {
        return Domain::create(array_merge([
            'user_id' => $user->id,
            'name' => $name,
            'type' => str_contains($name, '.') && !str_starts_with($name, 'proxy-') ? 'subdomain' : 'main',
            'document_root' => '/var/www/' . $name . '/public',
            'php_version' => '8.3',
            'webserver' => 'nginx',
            'status' => 'active',
            'is_active' => true,
        ], $overrides));
    }

    public function test_domain_index_filters_by_name_and_document_root(): void
    {
        $user = $this->createUser();
        $this->createDomain($user, 'alpha.example.com', ['document_root' => '/var/www/special-app/public']);
        $this->createDomain($user, 'beta.example.com');
        $this->createDomain($user, 'main.test', ['type' => 'main']);

        Livewire::actingAs($user)
            ->test(DomainIndex::class)
            ->set('search', 'special-app')
            ->assertSee('alpha.example.com')
            ->assertDontSee('beta.example.com')
            ->set('search', '')
            ->set('typeFilter', 'main')
            ->assertSee('main.test')
            ->assertDontSee('beta.example.com')
            ->set('typeFilter', 'all')
            ->call('sortByColumn', 'name');
    }

    public function test_sorting_toggles_direction(): void
    {
        $user = $this->createUser();

        Livewire::actingAs($user)
            ->test(DomainIndex::class)
            ->call('sortByColumn', 'name')
            ->assertSet('sortBy', 'name')
            ->assertSet('sortDir', 'asc')
            ->call('sortByColumn', 'name')
            ->assertSet('sortDir', 'desc');
    }

    public function test_bulk_suspend_calls_service_for_selected_domains(): void
    {
        $user = $this->createUser();
        $first = $this->createDomain($user, 'one.example.com');
        $second = $this->createDomain($user, 'two.example.com');

        $service = Mockery::mock(DomainService::class);
        $service->shouldReceive('suspend')->twice();

        Livewire::actingAs($user)
            ->test(DomainIndex::class)
            ->set('selected', [$first->id, $second->id])
            ->call('bulkSuspend', $service)
            ->assertSet('selected', [])
            ->assertSee('2 dominio(s) suspendido(s).');
    }

    public function test_export_csv_returns_download_with_selected_domains(): void
    {
        $user = $this->createUser();
        $domain = $this->createDomain($user, 'export.example.com');

        Livewire::actingAs($user)
            ->test(DomainIndex::class)
            ->set('selected', [$domain->id])
            ->call('exportCsv')
            ->assertFileDownloaded();
    }

    public function test_show_config_and_deploy_use_domain_service(): void
    {
        $user = $this->createUser();
        $domain = $this->createDomain($user, 'config.example.com');

        $service = Mockery::mock(DomainService::class);
        $service->shouldReceive('generateNginxConfig')->once()
            ->with(Mockery::on(fn(Domain $model) => $model->id === $domain->id))
            ->andReturn('server_name config.example.com;');
        $service->shouldReceive('deployConfigs')->once()
            ->with(Mockery::on(fn(Domain $model) => $model->id === $domain->id));

        Livewire::actingAs($user)
            ->test(DomainIndex::class)
            ->call('showConfig', $domain->id, $service)
            ->assertSet('configContent', 'server_name config.example.com;')
            ->call('deployDomain', $domain->id, $service)
            ->assertSee('Configuración de config.example.com desplegada correctamente.');
    }

    public function test_renew_domain_ssl_uses_ssl_service(): void
    {
        $user = $this->createUser();
        $domain = $this->createDomain($user, 'ssl.example.com', ['ssl_enabled' => true]);

        $service = Mockery::mock(SslService::class);
        $service->shouldReceive('renewCertificate')->once()
            ->with(Mockery::on(fn(Domain $model) => $model->id === $domain->id));

        Livewire::actingAs($user)
            ->test(DomainIndex::class)
            ->call('renewDomainSsl', $domain->id, $service)
            ->assertSee('Certificado de ssl.example.com renovado correctamente.');
    }

    public function test_check_domain_sets_http_health_result(): void
    {
        $user = $this->createUser();
        $domain = $this->createDomain($user, 'example.com', ['ssl_enabled' => true]);

        Http::fake([
            'https://example.com' => Http::response('OK', 200),
        ]);

        Livewire::actingAs($user)
            ->test(DomainIndex::class)
            ->call('checkDomain', $domain->id)
            ->assertSet('healthResult.ok', true)
            ->assertSet('healthResult.status', 200);
    }

    public function test_domain_health_flags_expiring_ssl(): void
    {
        $user = $this->createUser();
        $domain = $this->createDomain($user, 'expiring.example.com', [
            'ssl_enabled' => true,
            'ssl_expires_at' => now()->addDays(5),
        ]);

        $health = app(DomainIndex::class)->domainHealth($domain);

        $this->assertSame('warning', $health['level']);
        $this->assertStringContainsString('SSL expira', $health['label']);
    }
}
