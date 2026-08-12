<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for Phase 11.1.1 (API IDOR) and Phase 11.1.2 (WordPress RCE exposure).
 */
class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_access_account_management_api(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/accounts/1/suspend')
            ->assertStatus(403);
    }

    public function test_admin_can_suspend_a_client_account(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/accounts/{$client->id}/suspend")
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $client->id, 'is_active' => false]);
    }

    public function test_admin_cannot_suspend_another_admin_via_api(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $admin2 = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/accounts/{$admin2->id}/suspend")
            ->assertStatus(422);
    }

    public function test_client_cannot_access_wordpress_manager(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->get('/wordpress')->assertStatus(403);
    }

    public function test_admin_can_access_wordpress_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/wordpress')->assertOk();
    }
}