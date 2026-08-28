<?php

namespace App\Notifications;

use App\Models\User;

class SuspiciousLoginNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected User $user,
        protected string $ip,
        protected ?string $previousIp = null,
    ) {}

    protected function message(): string
    {
        $previous = $this->previousIp ?: 'none';
        $email = $this->user->email ?: 'unknown';

        return "SUSPICIOUS LOGIN DETECTED\n"
            . "User: {$email}\n"
            . "New IP: {$this->ip}\n"
            . "Previous IP: {$previous}";
    }
}
