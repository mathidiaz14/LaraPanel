<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Shell\SudoExecutor;
use App\Shell\ShellExecutor;
use Illuminate\Support\Facades\File;

class Settings extends Component
{
    // General Settings tab state
    public string $activeTab = 'updates'; // updates | general
    
    // Updates tab state
    public bool $isUpdateAvailable = false;
    public string $currentCommitHash = '';
    public string $currentCommitMessage = '';
    public string $latestCommitHash = '';
    public string $latestCommitMessage = '';
    public array $pendingCommits = [];
    
    // Update execution state
    public bool $isUpdating = false;
    public string $updateLog = '';
    public string $updateStatus = 'idle'; // idle | running | success | failed
    
    public string $successMessage = '';
    public string $errorMessage = '';

    public function mount(): void
    {
        $this->checkForUpdates();
    }

    /**
     * Check if there are updates available on GitHub by inspecting Git status
     */
    public function checkForUpdates(): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
        
        // Clear cached update flag to force fresh check
        \App\Services\UpdateService::clearCache();

        try {
            $executor = new ShellExecutor();
            $baseDir = base_path();

            // Fetch latest information from remote
            $executor->inDirectory($baseDir)->run(['git', 'fetch', 'origin'], false);

            // Get current active branch
            $branchResult = $executor->inDirectory($baseDir)->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
            $branch = trim($branchResult->stdout);

            // Get local commit info
            $localHashRes = $executor->inDirectory($baseDir)->run(['git', 'rev-parse', 'HEAD']);
            $this->currentCommitHash = trim($localHashRes->stdout);
            
            $localMsgRes = $executor->inDirectory($baseDir)->run(['git', 'log', '-1', '--pretty=%B']);
            $this->currentCommitMessage = trim($localMsgRes->stdout);

            // Get remote commit info
            $remoteHashRes = $executor->inDirectory($baseDir)->run(['git', 'rev-parse', "origin/{$branch}"]);
            $this->latestCommitHash = trim($remoteHashRes->stdout);

            if ($this->currentCommitHash !== $this->latestCommitHash) {
                $this->isUpdateAvailable = true;
                
                // Get pending commits list
                $pendingRes = $executor->inDirectory($baseDir)->run(['git', 'log', "HEAD..origin/{$branch}", '--oneline']);
                $this->pendingCommits = array_filter(explode("\n", trim($pendingRes->stdout)));

                // Get latest remote message
                $remoteMsgRes = $executor->inDirectory($baseDir)->run(['git', 'log', '-1', "origin/{$branch}", '--pretty=%B']);
                $this->latestCommitMessage = trim($remoteMsgRes->stdout);
            } else {
                $this->isUpdateAvailable = false;
                $this->pendingCommits = [];
                $this->latestCommitHash = $this->currentCommitHash;
                $this->latestCommitMessage = $this->currentCommitMessage;
            }
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al buscar actualizaciones: " . $e->getMessage();
        }
    }

    /**
     * Start the update process in the background.
     */
    public function startUpdate(): void
    {
        if ($this->isUpdating) {
            return;
        }

        $this->isUpdating = true;
        $this->updateStatus = 'running';
        $this->updateLog = "Iniciando proceso de actualización del panel...\n";

        $logFile = storage_path('logs/update_run.log');
        $pidFile = storage_path('logs/update_run.pid');

        // Clear files
        @file_put_contents($logFile, "LaraPanel Update Log\n=================================\n");
        @file_put_contents($pidFile, "");

        try {
            // We run it as background process redirecting output to log file
            $scriptPath = base_path('update.sh');
            
            if (!file_exists($scriptPath)) {
                throw new \RuntimeException("El script update.sh no se encuentra en " . $scriptPath);
            }

            // Command to run update in background
            $command = "nohup sudo -n {$scriptPath} > {$logFile} 2>&1 & echo \$! > {$pidFile}";
            
            $process = \Symfony\Component\Process\Process::fromShellCommandline($command);
            $process->run();

            $this->updateLog .= "Script de actualización lanzado en segundo plano. Monitorizando...\n";
        } catch (\Throwable $e) {
            $this->updateStatus = 'failed';
            $this->isUpdating = false;
            $this->errorMessage = "Error al lanzar actualización: " . $e->getMessage();
            $this->updateLog .= "ERROR: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Polling method called every second to read the log and check PID status.
     */
    public function pollUpdateStatus(): void
    {
        if (!$this->isUpdating) {
            return;
        }

        $logFile = storage_path('logs/update_run.log');
        $pidFile = storage_path('logs/update_run.pid');

        if (File::exists($logFile)) {
            $this->updateLog = File::get($logFile);
        }

        if (File::exists($pidFile)) {
            $pid = trim(File::get($pidFile));
            if ($pid !== '') {
                // Check if process is still running
                $isRunning = false;
                if (function_exists('posix_kill')) {
                    $isRunning = posix_kill((int)$pid, 0);
                } else {
                    // Fallback to ps command
                    $executor = new ShellExecutor();
                    $check = $executor->run(['ps', '-p', $pid], false);
                    $isRunning = $check->successful();
                }

                if (!$isRunning) {
                    // Check if git pull and final chown succeeded by checking end of log
                    $this->isUpdating = false;
                    
                    if (str_contains($this->updateLog, '¡LaraPanel se ha actualizado y optimizado correctamente') || str_contains($this->updateLog, 'successfully')) {
                        $this->updateStatus = 'success';
                        $this->successMessage = "¡LaraPanel se actualizó con éxito a la última versión!";
                        \App\Services\UpdateService::clearCache();
                        $this->checkForUpdates();
                    } else {
                        $this->updateStatus = 'failed';
                        $this->errorMessage = "El script de actualización finalizó pero con errores. Revisa la salida de la terminal.";
                    }
                    
                    // Cleanup pid file
                    @unlink($pidFile);
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.admin.settings')
            ->layout('layouts.app', [
                'title' => 'Configuración y Actualizaciones',
                'breadcrumb' => '<span>Administración</span> / <strong>Configuración</strong>'
            ]);
    }
}
