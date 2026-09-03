<?php

namespace App\Notifications;

class RamThresholdNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected float $usage,
        protected float $threshold,
    ) {}

    public function noticeType(): ?string
    {
        return 'ram_threshold';
    }

    protected function message(): string
    {
        return "RAM ALERT\n"
            . "El uso de RAM alcanzó el {$this->usage}%, "
            . "superando el umbral configurado de {$this->threshold}%.";
    }
}
