<?php

namespace App\Notifications;

class TelegramTextNotification extends LaraPanelTelegramNotification
{
    public function __construct(protected string $text) {}

    protected function message(): string
    {
        return $this->text;
    }
}
