<?php

namespace App\Listeners;

use App\Notifications\SuspiciousLoginNotification;
use App\Services\Notifier;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class NotifySuspiciousLogin
{
    /**
     * Handle the login event: detect a new/unusual IP and notify.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        $ip = Request::ip();
        $previousIp = $user->last_login_ip;

        // Persist current login metadata (silent to avoid model-event recursion).
        $user->last_login_at = now();
        $user->last_login_ip = $ip;
        $user->saveQuietly();

        // Only flag as suspicious when there is a known previous IP that differs.
        if ($previousIp !== null && $previousIp !== $ip) {
            Notifier::send(new SuspiciousLoginNotification($user, $ip, $previousIp));
        }
    }
}
