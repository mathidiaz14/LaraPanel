<?php

namespace App\Listeners;

use App\Notifications\LoginNotification;
use App\Notifications\SuspiciousLoginNotification;
use App\Services\Notifier;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class NotifySuspiciousLogin
{
    /**
     * Handle the login event: notify every successful login and flag logins
     * coming from a new/unusual IP.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        $ip = Request::ip();
        $userAgent = Request::userAgent() ?? '';
        $previousIp = $user->last_login_ip;

        // Persist current login metadata (silent to avoid model-event recursion).
        $user->last_login_at = now();
        $user->last_login_ip = $ip;
        $user->saveQuietly();

        // Always notify on a successful login.
        Notifier::send(new LoginNotification($user, $ip, $userAgent, $previousIp));

        // Additionally flag as suspicious when moving to a different IP.
        if ($previousIp !== null && $previousIp !== $ip) {
            Notifier::send(new SuspiciousLoginNotification($user, $ip, $previousIp));
        }
    }
}
