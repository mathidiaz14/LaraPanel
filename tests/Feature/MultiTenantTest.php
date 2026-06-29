<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_middleware_blocks_clients_from_admin_routes()
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)->get('/php');
        $response->assertStatus(403);
    }

    public function test_role_middleware_allows_admins_on_admin_routes()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/php');
        $response->assertOk();
    }

    public function test_impersonation_security_boundaries()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reseller1 = User::factory()->create(['role' => 'reseller']);
        $reseller2 = User::factory()->create(['role' => 'reseller']);
        
        $client1 = User::factory()->create(['role' => 'client', 'parent_id' => $reseller1->id]);
        $client2 = User::factory()->create(['role' => 'client', 'parent_id' => $reseller2->id]);

        // Reseller 1 CAN impersonate Client 1
        $response = $this->actingAs($reseller1)->get("/admin/impersonate/{$client1->id}");
        $response->assertRedirect('/');
        $this->assertEquals($client1->id, auth()->id());
        $this->assertTrue(session()->has('impersonated_by'));
        $this->assertEquals($reseller1->id, session()->get('impersonated_by'));

        // Stop impersonation
        $response = $this->get('/impersonate/stop');
        $response->assertRedirect('/admin/users');
        $this->assertEquals($reseller1->id, auth()->id());
        $this->assertFalse(session()->has('impersonated_by'));

        // Reseller 1 CANNOT impersonate Client 2 (different parent)
        $response = $this->actingAs($reseller1)->get("/admin/impersonate/{$client2->id}");
        $response->assertStatus(403);
    }
}
