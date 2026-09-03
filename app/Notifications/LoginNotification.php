<?php

namespace App\Notifications;

use App\Models\User;

class LoginNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected User $user,
        protected string $ip,
        protected string $userAgent = '',
        protected ?string $previousIp = null,
    ) {}

    public function noticeType(): ?string
    {
        return 'login';
    }

    protected function message(): string
    {
        $email = $this->user->email ?: 'unknown';
        $device = self::parseDevice($this->userAgent);

        $lines = ["🔐 Nuevo inicio de sesión en LaraPanel"];

        $lines[] = "Usuario: {$email}";

        if ($this->previousIp !== null && $this->previousIp !== $this->ip) {
            $lines[] = "IP: {$this->ip} (nueva, distinta de {$this->previousIp})";
        } else {
            $lines[] = "IP: {$this->ip}";
        }

        if ($device) {
            $lines[] = "Dispositivo: {$device}";
        }

        $lines[] = 'Hora: ' . now()->format('d/m/Y H:i:s');

        return implode("\n", $lines);
    }

    protected static function parseDevice(string $userAgent): string
    {
        if ($userAgent === '') {
            return '';
        }

        $mobile = stripos($userAgent, 'mobile') !== false;
        $os = '';

        if (stripos($userAgent, 'windows') !== false) $os = 'Windows';
        elseif (stripos($userAgent, 'mac os') !== false || stripos($userAgent, 'macintosh') !== false) $os = 'macOS';
        elseif (stripos($userAgent, 'android') !== false) $os = 'Android';
        elseif (stripos($userAgent, 'iphone') !== false || stripos($userAgent, 'ipad') !== false) $os = 'iOS';
        elseif (stripos($userAgent, 'linux') !== false) $os = 'Linux';

        $browser = '';
        if (stripos($userAgent, 'edg/') !== false) $browser = 'Edge';
        elseif (stripos($userAgent, 'chrome') !== false) $browser = 'Chrome';
        elseif (stripos($userAgent, 'firefox') !== false) $browser = 'Firefox';
        elseif (stripos($userAgent, 'safari') !== false) $browser = 'Safari';

        $kind = $mobile ? 'Móvil' : 'PC';

        return trim(trim("{$os} {$browser}") !== '' ? "{$os} {$browser} ({$kind})" : $kind);
    }
}
