<?php

namespace App\Notifications;

use App\Models\User;

class UserCreatedNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected User $created,
        protected string $by = '',
    ) {}

    public function noticeType(): ?string
    {
        return 'user_created';
    }

    protected function message(): string
    {
        $lines = ["👤 Nuevo usuario creado"];

        $lines[] = "Email: " . ($this->created->email ?: 'unknown');

        if ($this->by) {
            $lines[] = "Creado por: {$this->by}";
        }

        $lines[] = 'Hora: ' . now()->format('d/m/Y H:i:s');

        return implode("\n", $lines);
    }
}
