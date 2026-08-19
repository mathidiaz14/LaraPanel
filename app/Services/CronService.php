<?php

namespace App\Services;

use App\Models\CronJob;
use App\Models\CronRunLog;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\ShellExecutor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CronService
{
    public function __construct(
        protected ShellExecutor $shell,
    ) {}

    /**
     * Create a cron job and sync to system.
     */
    public function create(User $user, array $data): CronJob
    {
        // Simple schedule validation
        $schedule = trim($data['schedule']);
        $parts = explode(' ', $schedule);
        if (count($parts) !== 5) {
            throw new \InvalidArgumentException('Expresión cron inválida. Debe contener exactamente 5 campos.');
        }

        $cron = CronJob::create([
            'user_id' => $user->id,
            'label' => trim($data['label']),
            'command' => trim($data['command']),
            'schedule' => $schedule,
            'user' => 'www-data',
            'is_active' => true,
        ]);

        AuditLog::record('cron.created', $cron->label, ['command' => $cron->command]);

        try {
            $this->syncSystemCrontab();
        } catch (\Throwable $e) {
            // Keep DB and system crontab consistent if the sync fails.
            $cron->delete();
            throw $e;
        }

        return $cron;
    }

    /**
     * Execute a cron job and persist the result (run history + summary fields).
     *
     * Shared by the manual "Run Now" action and the automatic cron:run command.
     *
     * @return array{exit_code: int, status: string, output: string, duration_ms: int, skipped: bool}
     */
    public function executeJob(CronJob $cron, int $timeout = 3600): array
    {
        // Prevent overlapping executions of the same job (e.g. a `* * * * *`
        // job that takes longer than a minute).
        $lock = Cache::lock('cron_job_' . $cron->id, 3600);

        if (!$lock->get()) {
            return [
                'exit_code'   => 0,
                'status'      => 'skipped',
                'output'      => 'Ejecución omitida: otra instancia de esta tarea sigue en curso.',
                'duration_ms' => 0,
                'skipped'     => true,
            ];
        }

        try {
            $startMs = (int) (microtime(true) * 1000);

            // The PHP-FPM worker / scheduler already runs as www-data, so no
            // sudo/su is required. The command is passed raw to `sh -c` so
            // pipes, redirects and globs work exactly as they do under cron.
            $result = $this->shell
                ->withTimeout($timeout)
                ->run(['sh', '-c', $cron->command], checkExit: false);

            $exitCode = $result->exitCode;
            $output = trim($result->stdout . "\n" . $result->stderr) ?: '(sin salida)';
            $durationMs = (int) (microtime(true) * 1000) - $startMs;
            $status = ($exitCode === 0) ? 'success' : 'failure';

            CronRunLog::create([
                'cron_job_id' => $cron->id,
                'status'      => $status,
                'output'      => $output,
                'exit_code'   => $exitCode,
                'duration_ms' => $durationMs,
                'ran_at'      => now(),
            ]);

            $cron->increment('run_count');
            if ($status === 'failure') {
                $cron->increment('fail_count');
            }
            $cron->update([
                'last_run_at'     => now(),
                'last_run_status' => $status,
                'last_run_output' => $output,
            ]);

            return [
                'exit_code'   => $exitCode,
                'status'      => $status,
                'output'      => $output,
                'duration_ms' => $durationMs,
                'skipped'     => false,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Delete a cron job.
     */
    public function delete(CronJob $cron): void
    {
        AuditLog::record('cron.deleted', $cron->label);
        $cron->delete();
        $this->syncSystemCrontab();
    }

    /**
     * Toggle active state.
     */
    public function toggleStatus(CronJob $cron): void
    {
        $cron->update([
            'is_active' => !$cron->is_active,
        ]);

        AuditLog::record('cron.status.toggled', $cron->label, ['status' => $cron->is_active]);
        $this->syncSystemCrontab();
    }

    /**
     * Sync database entries to Linux crontab.
     */
    public function syncSystemCrontab(): void
    {
        $activeJobs = CronJob::where('is_active', true)->get();

        $crontabLines = [
            "# --- LaraPanel Generated Cron Jobs ---",
            "# DO NOT EDIT THIS BLOCK DIRECTLY - IT WILL BE OVERWRITTEN",
            // Cron runs with a minimal environment; make common binaries resolvable.
            "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
        ];

        foreach ($activeJobs as $job) {
            if (preg_match('/[\r\n\x00]/', $job->schedule) || ! preg_match('/\A[0-9*?,\/-]+(?:\s+[0-9*?,\/-]+){4}\z/', trim($job->schedule))) {
                throw new \InvalidArgumentException("La expresión cron de '{$job->label}' no es válida.");
            }

            if (preg_match('/[\r\n\x00]/', $job->command)) {
                throw new \InvalidArgumentException("El comando de '{$job->label}' contiene saltos de línea no permitidos.");
            }

            // Cron runs the line through /bin/sh. Instead of writing the raw
            // command (which would not be tracked), route it through the panel's
            // runner so every automatic execution is recorded in the DB.
            $crontabLines[] = sprintf(
                '%s cd %s && php artisan cron:run %d --no-interaction # LaraPanel_Job_ID_%d',
                $job->schedule,
                escapeshellarg(base_path()),
                $job->id,
                $job->id,
            );
        }
        $crontabLines[] = "# --- End LaraPanel Generated Cron Jobs ---";

        $crontabContent = implode("\n", $crontabLines) . "\n";

        if (!app()->isProduction()) {
            // Write locally in development
            $devDir = storage_path('app/cron');
            if (!is_dir($devDir)) {
                mkdir($devDir, 0775, true);
            }
            file_put_contents($devDir . '/crontab.txt', $crontabContent);
            return;
        }

        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'lp_cron_');
            file_put_contents($tmpFile, $crontabContent);

            // Install www-data's crontab explicitly, so the sync works the same
            // whether it is triggered from the PHP-FPM worker (www-data) or
            // from the CLI as any user with crontab privileges.
            $result = $this->shell->run(['crontab', '-u', 'www-data', $tmpFile], checkExit: false);

            @unlink($tmpFile);

            if ($result->failed()) {
                $message = trim($result->stderr ?: $result->stdout);
                throw new \RuntimeException("crontab: {$message}");
            }
        } catch (\Throwable $e) {
            Log::error("CronService: Failed to sync crontab: " . $e->getMessage());
            throw new \RuntimeException("No se pudo sincronizar el crontab del sistema: " . $e->getMessage());
        }
    }
}
