<?php

namespace App\Notifications;

class FailedLoginNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected string $ip,
        protected string $userAgent = '',
        protected ?string $email = null,
    ) {}

    public function noticeType(): ?string
    {
        return 'login_failed';
    }

    protected function message(): string
    {
        $lines = ["⚠️ Intento de inicio de sesión fallido"];

        if ($this->email) {
            $lines[] = "Usuario: {$this->email}";
        }

        $lines[] = "IP: {$this->ip}";

        // Compose a readable device/browser string from the user agent.
        $ua = $this->userAgent;
        if ($ua !== '') {
            $device = self::parseDevice($ua);
            if ($device) {
                $lines[] = "Dispositivo: {$device}";
            }
        }

        $lines[] = 'Hora: ' . now()->format('d/m/Y H:i:s');

        return implode("\n", $lines);
    }

    protected static function parseDevice(string $userAgent): string
    {
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
        $desc = trim("{$os} {$browser}");
        return $desc !== '' ? "{$desc} ({$kind})" : $kind;
    }
}
