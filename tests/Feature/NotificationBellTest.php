<?php

namespace Tests\Feature;

use App\Livewire\Notifications\NotificationBell;
use App\Models\PanelNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_only_shows_and_updates_current_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = PanelNotification::create([
            'user_id' => $user->id,
            'title' => 'Aviso propio',
            'message' => 'Todo correcto',
        ]);
        $theirs = PanelNotification::create([
            'user_id' => $other->id,
            'title' => 'Aviso ajeno',
            'message' => 'No debería verse',
        ]);

        $this->actingAs($user);

        Livewire::test(NotificationBell::class)
            ->assertSee('Aviso propio')
            ->assertDontSee('Aviso ajeno')
            ->assertSet('unreadCount', 1)
            ->call('markAsRead', $mine->id)
            ->assertSet('unreadCount', 0)
            ->call('delete', $mine->id)
            ->call('delete', $theirs->id);

        $this->assertDatabaseHas('panel_notifications', ['id' => $theirs->id]);
        $this->assertDatabaseMissing('panel_notifications', ['id' => $mine->id]);
    }

    public function test_mark_all_and_clear_read_are_scoped_to_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        PanelNotification::create([
            'user_id' => $user->id,
            'title' => 'Uno',
            'message' => 'Mensaje',
        ]);
        PanelNotification::create([
            'user_id' => $user->id,
            'title' => 'Dos',
            'message' => 'Mensaje',
            'read_at' => now(),
        ]);
        PanelNotification::create([
            'user_id' => $other->id,
            'title' => 'Ajeno',
            'message' => 'Mensaje',
        ]);

        $this->actingAs($user);

        Livewire::test(NotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0)
            ->call('clearRead')
            ->assertSee('No hay notificaciones por ahora.');

        $this->assertDatabaseHas('panel_notifications', ['user_id' => $other->id, 'title' => 'Ajeno']);
        $this->assertDatabaseCount('panel_notifications', 1);
    }

    public function test_send_test_creates_notification_for_current_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(NotificationBell::class)
            ->call('sendTest')
            ->assertSet('open', true)
            ->assertSee('Notificación de prueba')
            ->assertSet('unreadCount', 1);
    }
}
