<?php

namespace App\Listeners;

use App\Notifications\FailedLoginNotification;
use App\Services\Notifier;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;

class NotifyFailedLogin
{
    /**
     * Number of failed attempts tolerated within the cooldown window before
     * sending an alert (avoids spamming on brute-force bursts).
     */
    private const ATTEMPT_BATCH = 5;

    /**
     * Cooldown between alerts (seconds).
     */
    private const COOLDOWN = 300;

    /**
     * Handle a failed login: alert after a burst of failures, throttled.
     */
    public function handle(Failed $event): void
    {
        $ip = Request::ip();
        if (! $ip) {
            return;
        }

        $counterKey = 'failed_login_count_' . md5($ip);
        $count = (int) Cache::get($counterKey, 0) + 1;

        Cache::put($counterKey, $count, now()->addMinutes(15));

        if ($count % self::ATTEMPT_BATCH !== 0) {
            return;
        }

        $lastKey = 'failed_login_alert_' . md5($ip);
        if (Cache::has($lastKey)) {
            return;
        }

        Notifier::send(new FailedLoginNotification(
            $ip,
            Request::userAgent() ?? '',
            is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : null
        ));

        Cache::put($lastKey, now(), now()->addSeconds(self::COOLDOWN));
    }
}
