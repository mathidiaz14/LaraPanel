<?php

namespace Tests\Feature;

use App\Livewire\Backups\BackupIndex;
use App\Livewire\Docker\DockerIndex;
use App\Livewire\SSL\SslIssue;
use App\Models\Backup;
use App\Models\Domain;
use App\Models\SslCertificate;
use App\Models\User;
use App\Services\BackupService;
use App\Services\DockerService;
use App\Services\SslService;
use App\Shell\ShellResult;
use App\Shell\SudoExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class SslDockerBackupIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $composePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composePath = sys_get_temp_dir() . '/larapanel-compose-test-' . uniqid();
        File::ensureDirectoryExists($this->composePath);
        config(['larapanel.docker.compose_base_path' => $this->composePath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->composePath);
        parent::tearDown();
    }

    protected function createUser(): User
    {
        return User::factory()->create();
    }

    protected function createDomain(User $user, string $name = 'example.com'): Domain
    {
        return Domain::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'main',
            'document_root' => '/var/www/' . $name . '/public_html',
            'php_version' => '8.3',
            'webserver' => 'nginx',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_ssl_issue_component_passes_selected_options_to_service(): void
    {
        $user = $this->createUser();
        $domain = $this->createDomain($user);

        $certificate = SslCertificate::create([
            'domain_id' => $domain->id,
            'provider' => 'letsencrypt',
            'challenge_type' => 'webroot',
            'status' => 'active',
            'certificate' => '(test certificate)',
            'private_key' => encrypt('(test key)'),
            'issued_at' => now(),
            'expires_at' => now()->addDays(90),
            'auto_renew' => true,
            'san_domains' => [$domain->name, 'www.' . $domain->name],
        ]);

        $ssl = Mockery::mock(SslService::class);
        $ssl->shouldReceive('issueLetsEncrypt')
            ->once()
            ->with(
                Mockery::on(fn ($value) => $value->id === $domain->id),
                ['api.example.com'],
                true,
                false,
                false,
            )
            ->andReturn($certificate);

        $this->app->instance(SslService::class, $ssl);

        $component = Livewire::actingAs($user)
            ->test(SslIssue::class)
            ->set('domainId', $domain->id)
            ->set('extraSans', ['api.example.com'])
            ->set('includeWww', true)
            ->set('isWildcard', false)
            ->set('viaDns', false)
            ->call('issue')
            ->assertSet('success', true)
            ->assertSet('errorMsg', '');

        $this->assertStringContainsString($domain->name, $component->get('successMsg'));
    }

    public function test_docker_compose_up_uses_normalized_project_name(): void
    {
        $shell = Mockery::mock(SudoExecutor::class);
        $shell->shouldReceive('withTimeout')->with(300)->andReturnSelf();
        $shell->shouldReceive('inDirectory')->with($this->composePath . '/mi-stack')->andReturnSelf();
        $shell->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function (array $command): bool {
                    return $command[0] === 'docker'
                        && $command[1] === 'compose'
                        && $command[2] === '-p'
                        && $command[3] === 'mi-stack'
                        && in_array('--remove-orphans', $command, true);
                }),
                false,
            )
            ->andReturn(new ShellResult(0, 'compose up ok', '', 'docker compose up'));

        $this->app->instance(SudoExecutor::class, $shell);

        $output = app(DockerService::class)->composeUp(
            'mi-stack',
            "services:\n  app:\n    image: nginx:alpine\n",
        );

        $this->assertSame('compose up ok', $output);
        $this->assertFileExists($this->composePath . '/mi-stack/docker-compose.yml');
    }

    public function test_deploy_compose_uses_domain_as_project_name(): void
    {
        $path = $this->composePath . '/deployed-app';
        File::ensureDirectoryExists($path);
        File::put($path . '/docker-compose.yml', "services:\n  app:\n    image: nginx:alpine\n");

        $shell = Mockery::mock(SudoExecutor::class);
        $shell->shouldReceive('withTimeout')->with(300)->andReturnSelf();
        $shell->shouldReceive('inDirectory')->with($path)->andReturnSelf();
        $shell->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function (array $command): bool {
                    return $command[0] === 'docker'
                        && $command[1] === 'compose'
                        && $command[2] === '-p'
                        && $command[3] === 'puente-conexion-mathiasdiaz-uy'
                        && $command[4] === 'up';
                }),
                false,
            )
            ->andReturn(new ShellResult(0, 'deploy ok', '', 'docker compose up'));

        $this->app->instance(SudoExecutor::class, $shell);

        $output = app(DockerService::class)->runComposeInPath(
            $path,
            'up',
            ['-d', '--remove-orphans'],
            'puente-conexion.mathiasdiaz.uy',
        );

        $this->assertSame('deploy ok', $output);
    }

    public function test_docker_lists_compose_projects_from_json_output(): void
    {
        $json = '['
            . '{"Name":"puente-conexion-mathiasdiaz-uy","Status":"running(1)","ConfigFiles":"/var/www/apps/puente/server/docker-compose.yml"},'
            . '{"Name":"server","Status":"running(1)","ConfigFiles":"/var/www/apps/remo/server/docker-compose.yml"}'
            . ']';

        $shell = Mockery::mock(SudoExecutor::class);
        $shell->shouldReceive('run')
            ->once()
            ->with(['docker', 'compose', 'ls', '--format', 'json'], true)
            ->andReturn(new ShellResult(0, $json, '', 'docker compose ls'));

        $this->app->instance(SudoExecutor::class, $shell);

        $projects = app(DockerService::class)->listComposeProjects();

        $this->assertCount(2, $projects);
        $this->assertSame('puente-conexion-mathiasdiaz-uy', $projects[0]['name']);
        $this->assertSame('server', $projects[1]['name']);
        $this->assertSame('/var/www/apps/remo/server/docker-compose.yml', $projects[1]['configFiles']);
    }

    public function test_docker_container_format_includes_compose_project_label(): void
    {
        $json = '{"id":"abc","name":"remo-v3-server","image":"nginx:alpine","status":"Up 2 hours","state":"running","ports":"","created":"2 hours ago","size":"0B","project":"server"}' . "\n";

        $shell = Mockery::mock(SudoExecutor::class);
        $shell->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function (array $command): bool {
                    return $command[0] === 'docker'
                        && $command[1] === 'ps'
                        && str_contains($command[4], 'com.docker.compose.project');
                }),
                true,
            )
            ->andReturn(new ShellResult(0, $json, '', 'docker ps'));

        $this->app->instance(SudoExecutor::class, $shell);

        $containers = app(DockerService::class)->listContainers();

        $this->assertCount(1, $containers);
        $this->assertSame('server', $containers[0]['project']);
    }

    public function test_docker_index_shows_project_actions_on_group_header(): void
    {
        $user = $this->createUser();

        $docker = Mockery::mock(DockerService::class);
        $docker->shouldReceive('isDaemonRunning')->andReturn(true);
        $docker->shouldReceive('listContainers')->andReturn([
            [
                'id' => 'abc',
                'name' => 'remo-v3-server',
                'image' => 'nginx:alpine',
                'status' => 'Up 2 hours',
                'state' => 'running',
                'ports' => '',
                'created' => '2 hours ago',
                'size' => '0B',
                'project' => 'server',
            ],
        ]);
        $docker->shouldReceive('listComposeProjects')->andReturn([
            [
                'name' => 'server',
                'status' => 'running(1)',
                'configFiles' => '/var/www/apps/remo/server/docker-compose.yml',
            ],
        ]);

        $this->app->instance(DockerService::class, $docker);

        Livewire::actingAs($user)
            ->test(DockerIndex::class)
            ->assertSee('server')
            ->assertSee('running(1)')
            ->assertSee('1 contenedor(es)')
            ->assertSee('Iniciar proyecto')
            ->assertSee('Ver logs del proyecto')
            ->assertDontSee('Proyectos Docker');
    }

    public function test_backup_index_creates_backup_through_service(): void
    {
        $user = $this->createUser();
        $backup = Backup::create([
            'user_id' => $user->id,
            'label' => 'Backup de prueba',
            'type' => 'full',
            'disk' => 'local',
            'status' => 'completed',
            'size_bytes' => 1024 * 1024,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $service = Mockery::mock(BackupService::class);
        $service->shouldReceive('create')
            ->once()
            ->with($user, Mockery::on(fn (array $data) => $data['type'] === 'full' && $data['disk'] === 'local'))
            ->andReturn($backup);

        $this->app->instance(BackupService::class, $service);

        Livewire::actingAs($user)
            ->test(BackupIndex::class)
            ->set('label', 'Backup de prueba')
            ->set('type', 'full')
            ->set('disk', 'local')
            ->call('runBackup')
            ->assertSet('successMessage', 'Backup «Backup de prueba» completado con éxito. Tamaño: 1 MB.');
    }

    public function test_backup_index_restores_only_own_backup(): void
    {
        $user = $this->createUser();
        $backup = Backup::create([
            'user_id' => $user->id,
            'label' => 'Backup restaurable',
            'type' => 'files',
            'disk' => 'local',
            'status' => 'completed',
            'filename' => 'backup.tar.gz',
        ]);

        $service = Mockery::mock(BackupService::class);
        $service->shouldReceive('restore')->once()->with(Mockery::on(fn ($value) => $value->id === $backup->id));

        $this->app->instance(BackupService::class, $service);

        Livewire::actingAs($user)
            ->test(BackupIndex::class)
            ->call('restoreBackup', $backup->id)
            ->assertSet('successMessage', 'Backup restaurado con éxito.');
    }
}
