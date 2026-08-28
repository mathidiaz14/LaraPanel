<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

abstract class LaraPanelTelegramNotification extends Notification
{
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
}
