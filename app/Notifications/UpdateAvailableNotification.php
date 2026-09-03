<?php

namespace App\Notifications;

class UpdateAvailableNotification extends LaraPanelTelegramNotification
{
    public function __construct(protected int $pendingCount = 1) {}

    public function noticeType(): ?string
    {
        return 'update_available';
    }

    protected function message(): string
    {
        $n = $this->pendingCount;

        return "🔄 Actualización del panel disponible\n"
            . "Hay {$n} nuevo" . ($n === 1 ? '' : 's') . " commit" . ($n === 1 ? '' : 's') . " pendiente" . ($n === 1 ? '' : 's') . " para instalar.\n"
            . 'Revisa la sección de Actualizaciones del panel.';
    }
}
