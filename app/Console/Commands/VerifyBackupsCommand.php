<?php

namespace App\Console\Commands;

use App\Services\BackupVerificationService;
use Illuminate\Console\Command;

class VerifyBackupsCommand extends Command
{
    protected $signature = 'panel:verify-backups';

    protected $description = 'Verifica la integridad de los últimos backups de base de datos restaurándolos en una base de datos temporal';

    public function handle(BackupVerificationService $service): int
    {
        $results = $service->verifyLatest(10);

        $verified = collect($results)->where('verified', true)->count();
        $failed = count($results) - $verified;

        $this->info("Verificación completada: {$verified} correcto(s), {$failed} fallido(s) de " . count($results) . " backup(s).");

        foreach ($results as $result) {
            $line = sprintf(
                '[%s] #%d %s — %s',
                $result['verified'] ? 'OK' : 'FAIL',
                $result['backup_id'],
                $result['label'],
                $result['message'],
            );
            $result['verified'] ? $this->line($line) : $this->error($line);
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}