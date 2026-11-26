<?php

namespace Tests\Feature;

use App\Livewire\Git\GitIndex;
use App\Models\GitDeployment;
use App\Models\User;
use App\Services\GitDeployDispatcher;
use App\Services\GitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class GitDeployManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $gitService = Mockery::mock(GitService::class);
        $gitService->shouldReceive('getRepoStatus')->andReturn([]);
        $this->app->instance(GitService::class, $gitService);
    }

    protected function deployment(User $user, array $attributes = []): GitDeployment
    {
        return GitDeployment::create(array_merge([
            'user_id' => $user->id,
            'domain_name' => 'example.com',
            'repository_url' => 'https://github.com/test/repo.git',
            'branch' => 'main',
            'deploy_path' => '/var/www/example.com/public_html',
            'deploy_script' => 'cd "$DEPLOY_PATH" && git pull',
            'auto_deploy' => true,
            'webhook_id' => 'webhook-' . $user->id . '-' . random_int(1, 9999),
            'webhook_secret' => 'secret',
        ], $attributes));
    }

    public function test_owner_can_create_repository_with_ssh_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(GitIndex::class)
            ->set('isCreating', true)
            ->set('domain_name', 'example.com')
            ->set('repository_url', 'git@github.com:test/repo.git')
            ->set('branch', 'main')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('git_deployments', [
            'user_id' => $user->id,
            'repository_url' => 'git@github.com:test/repo.git',
        ]);
    }

    public function test_cannot_select_or_delete_another_users_deployment(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $deployment = $this->deployment($other);

        $this->actingAs($user);

        Livewire::test(GitIndex::class)
            ->call('selectDeployment', $deployment->id)
            ->assertSet('selectedDeployment', null)
            ->call('delete');

        $this->assertDatabaseHas('git_deployments', ['id' => $deployment->id]);
    }

    public function test_manual_deploy_is_dispatched_in_background(): void
    {
        $user = User::factory()->create();
        $deployment = $this->deployment($user);
        $this->actingAs($user);

        $dispatcher = Mockery::mock(GitDeployDispatcher::class);
        $dispatcher->shouldReceive('isRunning')->once()->with($deployment->id)->andReturn(false);
        $dispatcher->shouldReceive('dispatch')->once()->with($deployment->id, 'manual', null);
        $this->app->instance(GitDeployDispatcher::class, $dispatcher);

        Livewire::test(GitIndex::class)
            ->call('selectDeployment', $deployment->id)
            ->call('deployNow')
            ->assertSet('isDeploying', true);
    }

    public function test_redeploy_uses_original_commit_hash(): void
    {
        $user = User::factory()->create();
        $deployment = $this->deployment($user);
        $log = $deployment->logs()->create([
            'status' => 'success',
            'commit_hash' => 'abcdef1234567890',
            'triggered_by' => 'webhook',
        ]);
        $this->actingAs($user);

        $dispatcher = Mockery::mock(GitDeployDispatcher::class);
        $dispatcher->shouldReceive('isRunning')->once()->with($deployment->id)->andReturn(false);
        $dispatcher->shouldReceive('dispatch')->once()->with($deployment->id, 'manual', 'abcdef1234567890');
        $this->app->instance(GitDeployDispatcher::class, $dispatcher);

        Livewire::test(GitIndex::class)
            ->call('selectDeployment', $deployment->id)
            ->call('redeployCommit', $log->id)
            ->assertSet('isDeploying', true);
    }

    public function test_log_view_is_limited_to_selected_deployment(): void
    {
        $user = User::factory()->create();
        $deployment = $this->deployment($user);
        $otherDeployment = $this->deployment($user, ['domain_name' => 'other.com']);
        $log = $otherDeployment->logs()->create([
            'status' => 'failed',
            'triggered_by' => 'webhook',
        ]);
        $this->actingAs($user);

        Livewire::test(GitIndex::class)
            ->call('selectDeployment', $deployment->id)
            ->call('viewLog', $log->id)
            ->assertSet('selectedLog', null)
            ->assertSet('showLogModal', false);
    }

    public function test_commit_hash_validation_rejects_unsafe_values(): void
    {
        $service = (new \ReflectionClass(GitService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(GitService::class, 'normalizeCommit');

        $this->assertSame('abcdef1234', $method->invoke($service, 'ABCDEF1234'));

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($service, 'main; rm -rf /');
    }
}
