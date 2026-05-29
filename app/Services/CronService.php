<?php

namespace App\Services;

use App\Models\CronJob;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;

class CronService
{
    public function __construct(
        protected SudoExecutor $sudo,
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

        $this->syncSystemCrontab();

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
        ];

        foreach ($activeJobs as $job) {
            // Sanitize command string to prevent control injections
            $cmd = escapeshellcmd($job->command);
            // Append log output redirect or just standard job formatting
            $crontabLines[] = "{$job->schedule} {$cmd} # LaraPanel_Job_ID_{$job->id}";
        }
        $crontabLines[] = "# --- End LaraPanel Generated Cron Jobs ---";

        $crontabContent = implode("\n", $crontabLines) . "\n";

        if (!app()->isProduction()) {
            // Write locally in development
            $devCronFile = storage_path('app/public/crontab.txt');
            file_put_contents($devCronFile, $crontabContent);
            return;
        }

        try {
            $tmpFile = tempnam('/tmp', 'lp_cron_');
            file_put_contents($tmpFile, $crontabContent);
            
            // Apply crontab for www-data
            $this->sudo->run(['crontab', '-u', 'www-data', $tmpFile]);
            unlink($tmpFile);
        } catch (\Throwable $e) {
            Log::error("CronService: Failed to sync crontab: " . $e->getMessage());
            throw new \RuntimeException("No se pudo sincronizar el crontab del sistema: " . $e->getMessage());
        }
    }
}
