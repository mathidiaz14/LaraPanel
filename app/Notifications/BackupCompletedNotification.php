<?php

namespace App\Notifications;

class BackupCompletedNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected string $label,
        protected ?int $sizeBytes = null,
        protected string $type = 'full',
    ) {}

    public function noticeType(): ?string
    {
        return 'backup_completed';
    }

    protected function message(): string
    {
        $lines = ["✅ Backup completado"];

        $lines[] = "Backup: {$this->label}";
        $lines[] = "Tipo: {$this->type}";

        if ($this->sizeBytes !== null) {
            $lines[] = 'Tamaño: ' . self::humanSize($this->sizeBytes);
        }

        $lines[] = 'Hora: ' . now()->format('d/m/Y H:i:s');

        return implode("\n", $lines);
    }

    protected static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        return round($value, 2) . ' ' . $units[$i];
    }
}
