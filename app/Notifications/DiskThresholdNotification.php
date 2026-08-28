<?php

namespace App\Notifications;

class DiskThresholdNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected float $usage,
        protected float $threshold,
        protected ?float $used = null,
        protected ?float $total = null,
    ) {}

    protected function message(): string
    {
        $size = '';
        if ($this->used !== null && $this->total !== null) {
            $size = " ({$this->used} / {$this->total} GB)";
        }

        return "DISK USAGE ALERT\n"
            . "Disk usage is at {$this->usage}%{$size}, "
            . "exceeding the configured threshold of {$this->threshold}%.";
    }
}
