<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorEnforcedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_2fa_is_redirected_to_profile_on_admin_route()
    {
        $admin = User::factory()->create([
            'role'              => 'admin',
            'two_factor_enabled' => false,
        ]);

        $response = $this->actingAs($admin)->get('/servers');

        $response->assertRedirect(route('profile'));
    }

    public function test_admin_with_2fa_can_access_admin_route()
    {
        $admin = User::factory()->create([
            'role'               => 'admin',
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($admin)->get('/servers')->assertStatus(200);
    }

    public function test_client_is_unaffected_by_2fa_enforcement()
    {
        $client = User::factory()->create([
            'role'               => 'client',
            'two_factor_enabled' => false,
        ]);

        // A client (non-admin/reseller) is not subject to the 2FA middleware and
        // can reach their own panel routes normally.
        $this->actingAs($client)->get('/domains')->assertStatus(200);
    }
}
