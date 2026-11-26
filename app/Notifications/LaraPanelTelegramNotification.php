<?php

namespace App\Notifications;

use App\Services\NotificationPreferences;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

abstract class LaraPanelTelegramNotification extends Notification
{
    /**
     * The preference key that gates this notification. Return null to always
     * send (ignored by the preference system). Subclasses may override this.
     */
    public function noticeType(): ?string
    {
        return null;
    }

    /**
     * Deliver only through the Telegram channel.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    /**
     * Build the Telegram message, pulling token/chat_id from LaraPanel config.
     *
     * @param  mixed  $notifiable
     */
    public function toTelegram($notifiable): TelegramMessage
    {
        $token = config('larapanel.notifications.telegram.bot_token');
        $chatId = config('larapanel.notifications.telegram.chat_id');

        return TelegramMessage::create($this->message())
            ->to($chatId)
            ->token($token);
    }

    /**
     * Return the plain-text body of the notification.
     */
    abstract protected function message(): string;

    /**
     * Payload used by the in-app notification inbox. Subclasses may override it.
     *
     * @return array<string, mixed>
     */
    public function inAppNotification(): array
    {
        $type = $this->noticeType();
        $title = $type
            ? (NotificationPreferences::types()[$type] ?? 'Notificación de LaraPanel')
            : 'Notificación de LaraPanel';

        return [
            'type' => $type,
            'title' => $title,
            'message' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($this->message())) ?? ''), 400),
            'action_url' => null,
            'data' => [],
        ];
    }
}
