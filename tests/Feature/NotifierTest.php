<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\LaraPanelTelegramNotification;
use App\Notifications\TelegramTextNotification;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_app_notification_is_created_even_when_telegram_is_disabled(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        config([
            'larapanel.notifications.telegram.enabled' => false,
            'larapanel.notifications.telegram.bot_token' => null,
            'larapanel.notifications.telegram.chat_id' => null,
        ]);

        Notifier::send(new TelegramTextNotification('Mensaje de prueba'));

        $this->assertDatabaseHas('panel_notifications', [
            'title' => 'Notificación de LaraPanel',
            'message' => 'Mensaje de prueba',
        ]);
        Notification::assertNothingSent();
    }

    public function test_disabled_preference_skips_in_app_and_telegram(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        Setting::set('notice_login', '0');
        config([
            'larapanel.notifications.telegram.enabled' => true,
            'larapanel.notifications.telegram.bot_token' => 'token',
            'larapanel.notifications.telegram.chat_id' => 'chat',
        ]);

        $notification = new class extends LaraPanelTelegramNotification
        {
            public function noticeType(): ?string
            {
                return 'login';
            }

            protected function message(): string
            {
                return 'Login detectado';
            }
        };

        Notifier::send($notification);

        $this->assertDatabaseCount('panel_notifications', 0);
        Notification::assertNothingSent();
    }
}
