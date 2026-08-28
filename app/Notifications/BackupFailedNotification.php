<?php

namespace App\Notifications;

class BackupFailedNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected string $label,
        protected string $error,
    ) {}

    protected function message(): string
    {
        return "BACKUP FAILED\n"
            . "Backup: {$this->label}\n"
            . "Error: {$this->error}";
    }
}
