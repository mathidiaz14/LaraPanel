<?php

namespace App\Notifications;

class DomainChangedNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected string $action,   // created | deleted
        protected string $domain,
        protected string $by = '',
    ) {}

    public function noticeType(): ?string
    {
        return 'domain_changed';
    }

    protected function message(): string
    {
        $verb = strtolower($this->action) === 'deleted' ? 'eliminado' : 'creado';
        $icon = strtolower($this->action) === 'deleted' ? '🗑️' : '🌐';

        $lines = ["{$icon} Dominio {$verb}"];

        $lines[] = "Dominio: {$this->domain}";

        if ($this->by) {
            $lines[] = "Por: {$this->by}";
        }

        $lines[] = 'Hora: ' . now()->format('d/m/Y H:i:s');

        return implode("\n", $lines);
    }
}
