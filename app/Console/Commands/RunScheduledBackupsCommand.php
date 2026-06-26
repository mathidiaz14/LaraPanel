<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BackupSchedule;
use App\Services\BackupService;
use Carbon\Carbon;

class RunScheduledBackupsCommand extends Command
{
    protected $signature = 'backups:run-scheduled';
    protected $description = 'Runs all due backup schedules';

    public function handle(BackupService $backupService)
    {
        $this->info("Checking for due backup schedules...");

        // Fetch active schedules that are due (next_run_at is null or in the past)
        $schedules = BackupSchedule::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                      ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        if ($schedules->isEmpty()) {
            $this->info("No backups scheduled for right now.");
            return;
        }

        foreach ($schedules as $schedule) {
            $this->info("Running schedule #{$schedule->id} for User #{$schedule->user_id}");
            
            try {
                // Determine label based on frequency
                $label = "Auto Backup (" . ucfirst($schedule->frequency) . ") " . now()->format('Y-m-d');
                
                // Create backup using the service (synchronously or dispatched)
                // Since this runs in CLI via cron, synchronous is fine as there is no timeout
                $backupService->create($schedule->user, [
                    'type'      => $schedule->type,
                    'disk'      => $schedule->disk,
                    'domain_id' => $schedule->domain_id,
                    'label'     => $label,
                    'notes'     => 'Created automatically by BackupScheduler',
                ]);
                
                // Purge old backups based on retention count
                $this->enforceRetention($schedule, $backupService);
                
                // Calculate next run time
                $nextRun = $this->calculateNextRun($schedule->frequency);
                
                $schedule->update([
                    'last_run_at' => now(),
                    'next_run_at' => $nextRun
                ]);
                
                $this->info("Success. Next run at {$nextRun}");
            } catch (\Throwable $e) {
                $this->error("Failed to run schedule #{$schedule->id}: " . $e->getMessage());
                // We don't advance the next_run_at if it completely fails? 
                // Or we do advance it so it doesn't get stuck in a loop trying every minute?
                // Best practice: advance it so it retries next cycle, or add retry logic.
                $schedule->update([
                    'next_run_at' => now()->addMinutes(15) // Retry in 15 mins
                ]);
            }
        }
    }
    
    protected function calculateNextRun(string $frequency): Carbon
    {
        return match ($frequency) {
            'daily'   => now()->addDay()->startOfDay()->addHours(3), // 3 AM next day
            'weekly'  => now()->addWeek()->startOfWeek()->addHours(3),
            'monthly' => now()->addMonth()->startOfMonth()->addHours(3),
            default   => now()->addDay(),
        };
    }
    
    protected function enforceRetention(BackupSchedule $schedule, BackupService $backupService): void
    {
        if ($schedule->retention_count <= 0) return;
        
        $backups = \App\Models\Backup::where('user_id', $schedule->user_id)
            ->where('domain_id', $schedule->domain_id) // Same domain scope
            ->where('type', $schedule->type)
            ->where('disk', $schedule->disk)
            ->where('notes', 'Created automatically by BackupScheduler') // Only auto ones
            ->orderBy('created_at', 'desc')
            ->get();
            
        // If we have more than retention, delete the oldest
        if ($backups->count() > $schedule->retention_count) {
            $toDelete = $backups->slice($schedule->retention_count);
            foreach ($toDelete as $oldBackup) {
                try {
                    $backupService->delete($oldBackup);
                    $this->info("Deleted old backup #{$oldBackup->id} to enforce retention limit ({$schedule->retention_count})");
                } catch (\Throwable $e) {
                    $this->error("Failed to delete old backup #{$oldBackup->id}: " . $e->getMessage());
                }
            }
        }
    }
}
