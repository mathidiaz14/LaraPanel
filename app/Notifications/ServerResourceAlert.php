<?php

namespace App\Notifications;

class ServerResourceAlert extends LaraPanelTelegramNotification
{
    public string $resourceType;
    public float $usage;
    public string $detail;

    public function __construct(string $resourceType, float $usage, string $detail)
    {
        $this->resourceType = $resourceType;
        $this->usage = $usage;
        $this->detail = $detail;
    }

    public function noticeType(): ?string
    {
        return strtolower($this->resourceType) === 'ram' ? 'ram_threshold' : null;
    }

    protected function message(): string
    {
        return "🚨 ALERTA CRÍTICA DE SERVIDOR\n\n"
            . "Recurso: {$this->resourceType}\n"
            . "Uso actual: {$this->usage}%\n\n"
            . $this->detail;
    }

    public function inAppNotification(): array
    {
        return array_merge(parent::inAppNotification(), [
            'title' => 'Uso elevado de ' . $this->resourceType,
            'action_url' => route('dashboard'),
            'data' => [
                'resource_type' => $this->resourceType,
                'usage' => $this->usage,
                'detail' => $this->detail,
            ],
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'resource_type' => $this->resourceType,
            'usage' => $this->usage,
            'message' => $this->detail,
        ];
    }
}
