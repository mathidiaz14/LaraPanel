<?php

namespace App\Livewire\Cron;

use App\Models\CronJob;
use App\Models\CronRunLog;
use App\Services\CronService;
use App\Shell\SudoExecutor;
use Livewire\Component;

class CronIndex extends Component
{
    // Form fields
    public string $label = '';
    public string $command = '';
    public string $schedule = '* * * * *';
    public string $preset = 'custom';

    // View output modal
    public ?int $viewOutputId = null;
    public ?int $viewHistoryId = null;

    // Success/error alerts
    public string $successMessage = '';
    public string $errorMessage = '';

    protected array $rules = [
        'label' => 'required|string|min:3|max:64',
        'command' => 'required|string|min:3|max:255',
        'schedule' => 'required|string|regex:/^[\*0-9,\-\/]+( [\*0-9,\-\/]+){4}$/',
    ];

    public function updatedPreset(string $value): void
    {
        switch ($value) {
            case 'every_minute':
                $this->schedule = '* * * * *';
                break;
            case 'every_5_minutes':
                $this->schedule = '*/5 * * * *';
                break;
            case 'hourly':
                $this->schedule = '0 * * * *';
                break;
            case 'daily':
                $this->schedule = '0 0 * * *';
                break;
            case 'weekly':
                $this->schedule = '0 0 * * 0';
                break;
            case 'monthly':
                $this->schedule = '0 0 1 * *';
                break;
        }
    }

    public function createCron(CronService $cronService): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            if (!auth()->user()->canAddCronJob()) {
                $this->errorMessage = 'Has alcanzado el límite de tareas programadas (cron) de tu plan.';
                return;
            }

            $cronService->create(auth()->user(), [
                'label' => $this->label,
                'command' => $this->command,
                'schedule' => $this->schedule,
            ]);

            $this->successMessage = "Tarea programada (cron) creada con éxito.";
            
            // Reset
            $this->label = '';
            $this->command = '';
            $this->schedule = '* * * * *';
            $this->preset = 'custom';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteCron(int $id, CronService $cronService): void
    {
        $cron = CronJob::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $cronService->delete($cron);
            $this->successMessage = "Tarea programada eliminada con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleStatus(int $id, CronService $cronService): void
    {
        $cron = CronJob::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $cronService->toggleStatus($cron);
            $this->successMessage = "Estado de la tarea programada actualizado.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function runJobNow(int $id, SudoExecutor $sudo): void
    {
        $cron = CronJob::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            $cmd = escapeshellcmd($cron->command);
            $startMs = (int)(microtime(true) * 1000);

            if (app()->isProduction()) {
                $result = $sudo->run(['su', '-', 'www-data', '-s', '/bin/bash', '-c', $cmd], checkExit: false);
                $exitCode = $result->exitCode;
                $output = $result->stdout . "\n" . $result->stderr;
            } else {
                $output = [];
                $exitCode = 0;
                exec($cmd . ' 2>&1', $output, $exitCode);
                $output = implode("\n", $output) ?: '(sin salida)';
            }

            $durationMs = (int)(microtime(true) * 1000) - $startMs;
            $status = ($exitCode === 0) ? 'success' : 'failure';

            // Save to historical log
            CronRunLog::create([
                'cron_job_id' => $cron->id,
                'status'      => $status,
                'output'      => $output,
                'exit_code'   => $exitCode,
                'duration_ms' => $durationMs,
                'ran_at'      => now(),
            ]);

            // Update cron job summary fields
            $cron->increment('run_count');
            if ($status === 'failure') $cron->increment('fail_count');
            $cron->update([
                'last_run_at'     => now(),
                'last_run_status' => $status,
                'last_run_output' => $output,
            ]);

            $this->successMessage = "Ejecución finalizada con estado: " . strtoupper($status) . " ({$durationMs}ms)";
        } catch (\Throwable $e) {
            $this->errorMessage = "Error de ejecución: " . $e->getMessage();
        }
    }

    public function viewOutput(int $id): void
    {
        $this->viewOutputId = $id;
    }

    public function viewHistory(int $id): void
    {
        $this->viewHistoryId = $id;
        $this->viewOutputId = null;
    }

    public function render()
    {
        $jobs = CronJob::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        $runLogs = $this->viewHistoryId
            ? CronRunLog::where('cron_job_id', $this->viewHistoryId)
                ->orderBy('ran_at', 'desc')
                ->limit(50)
                ->get()
            : collect();

        return view('livewire.cron.cron-index', [
            'jobs'    => $jobs,
            'runLogs' => $runLogs,
        ])->layout('layouts.app', [
            'title'      => 'Tareas Programadas (Cron)',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Tareas Programadas</strong>',
        ]);
    }
}
