<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123')
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/'); // Fortify redirects to / by default
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123')
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_with_2fa_are_redirected_to_challenge()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'two_factor_secret' => encrypt('dummy-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['dummy-code-1'])),
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // Technically, they are authenticated into a temporary session but not fully authenticated for the app
        $response->assertRedirect('/two-factor-challenge');
    }
}
