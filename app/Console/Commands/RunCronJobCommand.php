<?php

namespace App\Console\Commands;

use App\Models\CronJob;
use App\Services\CronService;
use Illuminate\Console\Command;

class RunCronJobCommand extends Command
{
    protected $signature = 'cron:run {job : ID de la tarea cron}';

    protected $description = 'Ejecuta una tarea cron de LaraPanel y registra el resultado (usado por el crontab del sistema).';

    public function handle(CronService $cronService): int
    {
        $job = CronJob::find($this->argument('job'));

        if (!$job) {
            $this->error("Tarea cron no encontrada.");
            return 1;
        }

        if (!$job->is_active) {
            $this->info("Tarea cron #{$job->id} está pausada; se omite.");
            return 0;
        }

        $result = $cronService->executeJob($job);

        if ($result['status'] === 'skipped') {
            $this->line($result['output']);
            return 0;
        }

        $this->line($result['output']);

        return $result['exit_code'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}