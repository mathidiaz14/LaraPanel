<?php

namespace App\Notifications;

use App\Models\UptimeMonitor;

class UptimeDownNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected UptimeMonitor $monitor,
        protected ?string $error = null,
    ) {}

    protected function message(): string
    {
        $name = $this->monitor->name ?: ($this->monitor->target ?: 'Uptime Monitor');
        $target = $this->monitor->target ?: 'unknown';
        $error = $this->error ? " Error: {$this->error}" : '';

        return "UPTIME DOWN ALERT\n"
            . "Monitor: {$name}\n"
            . "Target: {$target}\n"
            . "Status: DOWN{$error}";
    }
}
