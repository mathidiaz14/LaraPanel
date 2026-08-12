<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\TerminalSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

/**
 * Phase 1 — Interactive terminal session lifecycle & authorization.
 */
class TerminalSessionTest extends TestCase
{
    use RefreshDatabase;

    // ── Creation (route authorization) ────────────────────────────────────────

    public function test_guest_cannot_create_terminal_session(): void
    {
        // Non-API routes redirect guests to login (app convention).
        $this->post('/terminal/session', ['type' => 'local'])
            ->assertRedirect(route('login'));
    }

    public function test_client_cannot_create_terminal_session(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->postJson('/terminal/session', ['type' => 'local'])
            ->assertStatus(403);
    }

    public function test_admin_can_create_local_terminal_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/terminal/session', ['type' => 'local'])
            ->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => ['session_id', 'token', 'channel', 'type', 'server'],
            ])
            ->assertJson(['status' => 'success', 'data' => ['type' => 'local']]);

        $this->assertDatabaseHas('terminal_sessions', [
            'user_id' => $admin->id,
            'type' => 'local',
            'status' => TerminalSession::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'terminal.session.start',
        ]);
    }

    public function test_admin_can_create_ssh_session_for_own_active_server(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::create([
            'user_id' => $admin->id,
            'name' => 'VPS NYC',
            'hostname' => '203.0.113.10',
            'username' => 'root',
            'auth_type' => 'key',
            'is_local' => false,
            'is_active' => true,
            'status' => 'online',
        ]);

        $this->actingAs($admin)
            ->postJson('/terminal/session', ['type' => 'ssh', 'server_id' => $server->id])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'ssh')
            ->assertJsonPath('data.server.id', $server->id);
    }

    public function test_ssh_session_rejects_foreign_or_local_server(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'client']);

        $foreign = Server::create([
            'user_id' => $other->id, 'name' => 'A', 'hostname' => '203.0.113.11',
            'username' => 'root', 'auth_type' => 'key', 'is_local' => false, 'is_active' => true,
        ]);
        $local = Server::create([
            'user_id' => $admin->id, 'name' => 'B', 'hostname' => '127.0.0.1',
            'username' => 'root', 'auth_type' => 'key', 'is_local' => true, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson('/terminal/session', ['type' => 'ssh', 'server_id' => $foreign->id])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson('/terminal/session', ['type' => 'ssh', 'server_id' => $local->id])
            ->assertStatus(422);
    }

    public function test_terminal_can_be_disabled_by_config(): void
    {
        config(['larapanel.security.terminal.enabled' => false]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/terminal/session', ['type' => 'local'])
            ->assertStatus(403);
    }

    public function test_concurrent_session_limit_is_enforced(): void
    {
        config(['larapanel.security.terminal.max_concurrent_sessions' => 2]);

        $admin = User::factory()->create(['role' => 'admin']);

        TerminalSession::createForUser($admin->id, 'local');
        TerminalSession::createForUser($admin->id, 'local');

        $this->actingAs($admin)
            ->postJson('/terminal/session', ['type' => 'local'])
            ->assertStatus(429);
    }

    // ── Destruction ───────────────────────────────────────────────────────────

    public function test_admin_can_close_own_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $session = TerminalSession::createForUser($admin->id, 'local');

        $this->actingAs($admin)
            ->deleteJson("/terminal/session/{$session->id}")
            ->assertOk();

        $this->assertDatabaseHas('terminal_sessions', [
            'id' => $session->id,
            'status' => TerminalSession::STATUS_CLOSED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'terminal.session.close',
        ]);
    }

    public function test_admin_cannot_close_foreign_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'client']);
        $session = TerminalSession::createForUser($other->id, 'local');

        $this->actingAs($admin)
            ->deleteJson("/terminal/session/{$session->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('terminal_sessions', [
            'id' => $session->id,
            'status' => TerminalSession::STATUS_PENDING,
        ]);
    }

    // ── Broadcast channel authorization ───────────────────────────────────────

    private function configurePusherDriver(): void
    {
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => [
                'cluster' => 'mt1',
                'host' => '127.0.0.1',
                'port' => 6001,
                'scheme' => 'http',
                'useTLS' => false,
            ],
        ]);

        // The channel definition is registered during app boot against the
        // then-default broadcaster; re-register it (normalized, without the
        // `private-` prefix per Pusher conventions) on the pusher connection.
        Broadcast::channel('terminal.{channel}', function ($user, $channel) {
            return TerminalSession::canJoin($user, $channel);
        });
    }

    public function test_channel_authorized_for_owning_admin(): void
    {
        $this->configurePusherDriver();

        $admin = User::factory()->create(['role' => 'admin']);
        $session = TerminalSession::createForUser($admin->id, 'local');

        $this->actingAs($admin)
            ->json('POST', '/broadcasting/auth', [
                'socket_id' => '123.4567890',
                'channel_name' => $session->channelName(),
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_channel_denied_for_client(): void
    {
        $this->configurePusherDriver();

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $session = TerminalSession::createForUser($admin->id, 'local');

        $this->actingAs($client)
            ->json('POST', '/broadcasting/auth', [
                'socket_id' => '123.4567890',
                'channel_name' => $session->channelName(),
            ])
            ->assertStatus(403);
    }

    public function test_channel_denied_for_other_owner(): void
    {
        $this->configurePusherDriver();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin2 = User::factory()->create(['role' => 'admin']);
        $session = TerminalSession::createForUser($admin->id, 'local');

        $this->actingAs($admin2)
            ->json('POST', '/broadcasting/auth', [
                'socket_id' => '123.4567890',
                'channel_name' => $session->channelName(),
            ])
            ->assertStatus(403);
    }

    public function test_channel_denied_when_session_closed(): void
    {
        $this->configurePusherDriver();

        $admin = User::factory()->create(['role' => 'admin']);
        $session = TerminalSession::createForUser($admin->id, 'local');
        $session->close();

        $this->actingAs($admin)
            ->json('POST', '/broadcasting/auth', [
                'socket_id' => '123.4567890',
                'channel_name' => $session->channelName(),
            ])
            ->assertStatus(403);
    }
}
