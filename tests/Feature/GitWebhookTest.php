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
}
