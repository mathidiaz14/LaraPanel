<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_is_not_registered()
    {
        // Self-registration is disabled in config/fortify.php (Features::registration()
        // is commented out), so the /register route must not exist.
        $response = $this->post('/register', [
            'name'                  => 'Attacker',
            'email'                 => 'attacker@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_registration_get_screen_is_not_available()
    {
        $this->get('/register')->assertStatus(404);
    }
}
