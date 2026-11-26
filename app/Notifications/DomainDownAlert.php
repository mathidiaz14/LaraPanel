<?php

namespace App\Notifications;

class DomainDownAlert extends LaraPanelTelegramNotification
{
    public string $domainName;
    public string $errorReason;

    public function __construct(string $domainName, string $errorReason)
    {
        $this->domainName = $domainName;
        $this->errorReason = $errorReason;
    }

    public function noticeType(): ?string
    {
        return 'uptime_down';
    }

    protected function message(): string
    {
        return "⚠️ ALERTA DE DOMINIO\n\n"
            . "Sitio: {$this->domainName}\n"
            . "Error: {$this->errorReason}\n\n"
            . 'El sitio web no responde correctamente.';
    }

    public function inAppNotification(): array
    {
        return array_merge(parent::inAppNotification(), [
            'title' => 'Dominio caído: ' . $this->domainName,
            'action_url' => route('domains.index'),
            'data' => ['domain' => $this->domainName, 'error' => $this->errorReason],
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'domain' => $this->domainName,
            'error' => $this->errorReason,
        ];
    }
}
