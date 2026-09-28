<?php

namespace App\Notifications;

use App\Models\Domain;

class CertificateExpiringNotification extends LaraPanelTelegramNotification
{
    public function __construct(
        protected Domain $domain,
        protected int $daysLeft,
        protected bool $expired = false,
    ) {}

    public function noticeType(): ?string
    {
        return 'ssl_expiring';
    }

    protected function message(): string
    {
        $lines = [];

        if ($this->expired) {
            $lines[] = "🔴 Certificado SSL EXPIRADO";
        } else {
            $lines[] = "⚠️ Certificado SSL próximo a expirar";
        }

        $lines[] = 'Dominio: ' . $this->domain->name;

        if ($this->expired) {
            $lines[] = 'El certificado ya venció y HTTPS puede estar fallando.';
        } elseif ($this->daysLeft <= 1) {
            $lines[] = 'Expira HOY.';
        } else {
            $lines[] = "Expira en {$this->daysLeft} días.";
        }

        $lines[] = 'Fecha de expiración: ' . ($this->domain->ssl_expires_at?->format('d/m/Y') ?? 'Desconocida');
        $lines[] = 'Hora: ' . now()->format('d/m/Y H:i:s');

        return implode("\n", $lines);
    }
}