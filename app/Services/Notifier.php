<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\LaraPanelTelegramNotification;
use App\Notifications\TelegramTextNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class Notifier
{
    /**
     * Whether Telegram notifications are enabled and correctly configured.
     */
    public static function enabled(): bool
    {
        if (! config('larapanel.notifications.telegram.enabled')) {
            return false;
        }

        return ! empty(config('larapanel.notifications.telegram.bot_token'))
            && ! empty(config('larapanel.notifications.telegram.chat_id'));
    }

    /**
     * Send a structured Telegram notification. No-op when disabled/misconfigured.
     */
    public static function send(LaraPanelTelegramNotification $notification): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            Notification::send(new AnonymousNotifiable(), $notification);
        } catch (\Throwable $e) {
            Log::warning('Telegram notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Send a plain-text Telegram notification.
     */
    public static function notify(string $message, ?User $user = null): void
    {
        self::send(new TelegramTextNotification($message));
    }
}
