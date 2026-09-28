<?php

namespace App\Console\Commands;

use App\Services\CertificateMonitorService;
use Illuminate\Console\Command;

class CheckSslCertificatesCommand extends Command
{
    protected $signature = 'panel:check-ssl';

    protected $description = 'Detecta certificados SSL próximos a expirar y alerta a los administradores';

    public function handle(CertificateMonitorService $service): int
    {
        $alerted = $service->sendAlerts(14);

        $this->info("Escaneo SSL completado. Alertas enviadas: {$alerted}.");

        return self::SUCCESS;
    }
}