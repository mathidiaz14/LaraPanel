<?php

namespace Tests\Feature;

use App\Models\GitDeployment;
use App\Models\User;
use App\Services\GitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GitWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected GitDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        
        $user = User::factory()->create();
        
        $this->deployment = GitDeployment::create([
            'user_id' => $user->id,
            'domain_name' => 'test.com',
            'repository_url' => 'git@github.com:test/repo.git',
            'branch' => 'main',
            'deploy_path' => '/var/www/test.com/public_html',
            'auto_deploy' => true,
            'webhook_id' => 'test-uuid-1234',
            'webhook_secret' => 'my-secret-key'
        ]);
        
        // Mock GitService so we don't actually run git commands
        $gitServiceMock = Mockery::mock(GitService::class);
        $gitServiceMock->shouldReceive('deploy')->andReturn();
        $this->app->instance(GitService::class, $gitServiceMock);
    }

    public function test_it_rejects_missing_signature()
    {
        $response = $this->postJson("/api/webhooks/git/{$this->deployment->webhook_id}", [
            'ref' => 'refs/heads/main'
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature']);
    }

    public function test_it_accepts_valid_github_signature()
    {
        $payload = json_encode(['ref' => 'refs/heads/main']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'my-secret-key');

        $response = $this->postJson("/api/webhooks/git/{$this->deployment->webhook_id}", 
            ['ref' => 'refs/heads/main'],
            ['X-Hub-Signature-256' => $signature]
        );

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Deployment triggered successfully']);
    }

    public function test_it_ignores_push_to_different_branch()
    {
        $payload = json_encode(['ref' => 'refs/heads/development']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'my-secret-key');

        $response = $this->postJson("/api/webhooks/git/{$this->deployment->webhook_id}", 
            ['ref' => 'refs/heads/development'],
            ['X-Hub-Signature-256' => $signature]
        );

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Push to branch development ignored. Tracking branch is main.']);
    }

    public function test_it_rejects_when_auto_deploy_is_disabled()
    {
        $this->deployment->update(['auto_deploy' => false]);
        
        $response = $this->postJson("/api/webhooks/git/{$this->deployment->webhook_id}");
        
        $response->assertStatus(400);
        $response->assertJson(['message' => 'Auto-deploy is disabled for this repository']);
    }

    public function test_empty_secret_is_rejected_with_401()
    {
        // An empty webhook_secret must never be treated as a valid signature.
        // Clear the secret that the factory/boot default populated.
        $this->deployment->update(['webhook_secret' => '']);
        $this->deployment->refresh();

        // Prevent any real process spawn while we assert on the response.
        $controller = \Mockery::mock(\App\Http\Controllers\GitWebhookController::class)->makePartial();
        $controller->shouldReceive('spawnDeploy')->andReturnUsing(fn () => null);
        $this->app->instance(\App\Http\Controllers\GitWebhookController::class, $controller);

        $payload = json_encode(['ref' => 'refs/heads/main']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, '');

        $response = $this->postJson(
            "/api/webhooks/git/{$this->deployment->webhook_id}",
            ['ref' => 'refs/heads/main'],
            ['X-Hub-Signature-256' => $signature]
        );

        $response->assertStatus(401);
    }

    public function test_valid_secret_triggers_deploy_without_real_spawn()
    {
        // Mock the deploy spawn so the test never actually launches `php artisan`.
        $controller = \Mockery::mock(\App\Http\Controllers\GitWebhookController::class)->makePartial();
        $controller->shouldReceive('spawnDeploy')
            ->once()
            ->andReturnUsing(fn () => null);
        $this->app->instance(\App\Http\Controllers\GitWebhookController::class, $controller);

        $payload = json_encode(['ref' => 'refs/heads/main']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'my-secret-key');

        $response = $this->postJson(
            "/api/webhooks/git/{$this->deployment->webhook_id}",
            ['ref' => 'refs/heads/main'],
            ['X-Hub-Signature-256' => $signature]
        );

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Deployment triggered successfully']);
    }
}
