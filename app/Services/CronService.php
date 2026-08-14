<?php

namespace App\Services;

use App\Models\CronJob;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\ShellExecutor;
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

            // Cron runs the line through /bin/sh, so the command must be written
            // verbatim. escapeshellcmd() would break pipes, redirects and globs.
            $crontabLines[] = "{$job->schedule} {$job->command} # LaraPanel_Job_ID_{$job->id}";
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

            // The PHP-FPM worker already runs as www-data, so `crontab` can be
            // invoked directly (no sudo) to install www-data's crontab.
            $result = $this->shell->run(['crontab', $tmpFile], checkExit: false);

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
