<?php

namespace App\Livewire\Terminal;

use App\Jobs\ExecuteTerminalCommand;
use App\Models\AuditLog;
use App\Models\Server;
use App\Models\TerminalCommandHistory;
use App\Services\TerminalCommandPolicy;
use App\Services\TerminalService;
use App\Shell\RemoteShellExecutor;
use Livewire\Component;

class TerminalIndex extends Component
{
    public string $command = '';
    public string $cwd = '/var/www';
    public array $history = [];
    public array $quickCommands = [];
    public ?int $selectedServerId = null;
    public ?int $activeJobId = null;
    public string $jobStatus = '';
    public string $output = '';
    public ?int $exitCode = null;
    public ?int $durationMs = null;
    public bool $background = false;
    public bool $allowDangerous = false;
    public string $pendingCommand = '';
    public string $notice = '';
    public string $errorMessage = '';

    public function mount(): void
    {
        $this->selectedServerId = Server::forUser(auth()->id())->where('is_local', true)->value('id');
        $this->quickCommands = [
            ['label' => 'Directorio actual', 'command' => 'pwd', 'icon' => 'fa-location-dot'],
            ['label' => 'Lista detallada', 'command' => 'ls -la', 'icon' => 'fa-list'],
            ['label' => 'Espacio en disco', 'command' => 'df -h', 'icon' => 'fa-hard-drive'],
            ['label' => 'Memoria RAM', 'command' => 'free -h', 'icon' => 'fa-memory'],
            ['label' => 'Procesos', 'command' => 'ps aux', 'icon' => 'fa-microchip'],
            ['label' => 'Estado Git', 'command' => 'git status', 'icon' => 'fa-code-branch'],
        ];
        $this->loadHistory();
    }

    public function runCommand(TerminalCommandPolicy $policy, TerminalService $terminal): void
    {
        $this->errorMessage = '';
        $this->notice = '';

        try {
            $command = $policy->validate($this->command);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        if ($this->isDangerous($command) && ! $this->allowDangerous) {
            $this->pendingCommand = $command;
            $this->notice = 'Este comando puede modificar o eliminar datos. Confirma para continuar.';
            $this->dispatch('terminal-warning', message: $this->notice);
            return;
        }

        $this->allowDangerous = false;
        $this->pendingCommand = '';
        $this->command = '';

        if (in_array(strtolower($command), ['clear', 'cls'], true)) {
            $this->output = '';
            $this->dispatch('terminal-clear');
            return;
        }

        $history = TerminalCommandHistory::create([
            'user_id' => auth()->id(),
            'server_id' => $this->selectedServerId,
            'command' => $command,
            'cwd' => $this->cwd,
            'status' => $this->background ? 'queued' : 'running',
            'background' => $this->background,
            'started_at' => $this->background ? null : now(),
        ]);

        AuditLog::record('terminal.command.start', $command, [
            'history_id' => $history->id,
            'server_id' => $this->selectedServerId,
            'background' => $this->background,
        ]);

        if ($this->background) {
            $this->activeJobId = $history->id;
            $this->jobStatus = 'queued';
            ExecuteTerminalCommand::dispatch($history->id);
            $this->background = false;
            $this->output = "Trabajo #{$history->id} en cola. Esta pantalla se actualizará automáticamente.";
            $this->dispatch('terminal-output', output: $this->output, cwd: $this->cwd, code: null);
            $this->loadHistory();
            return;
        }

        $started = microtime(true);
        try {
            $server = $this->selectedServerId
                ? Server::forUser(auth()->id())->findOrFail($this->selectedServerId)
                : null;

            if ($server && ! $server->is_local) {
                $result = (new RemoteShellExecutor($server))
                    ->withTimeout(120)
                    ->inDirectory($this->cwd)
                    ->run($policy->tokens($command), false);
                $resultOutput = $result->stdout . $result->stderr;
                $code = $result->exitCode;
            } else {
                $result = $terminal->execute($command, $this->cwd);
                $resultOutput = $result['output'];
                $code = $result['code'];
                $this->cwd = $result['cwd'];
            }

            $this->output = $resultOutput;
            $this->exitCode = $code;
            $this->durationMs = (int) round((microtime(true) - $started) * 1000);
            $history->update([
                'status' => $code === 0 ? 'success' : 'failed',
                'output' => $resultOutput,
                'exit_code' => $code,
                'finished_at' => now(),
                'duration_ms' => $this->durationMs,
            ]);
        } catch (\Throwable $e) {
            $this->output = $e->getMessage();
            $this->exitCode = 1;
            $history->update([
                'status' => 'failed',
                'output' => $this->output,
                'exit_code' => 1,
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }

        $this->dispatch('terminal-output', output: $this->output, cwd: $this->cwd, code: $this->exitCode);
        $this->loadHistory();
    }

    public function confirmCommand(TerminalCommandPolicy $policy, TerminalService $terminal): void
    {
        if ($this->pendingCommand === '') return;
        $this->command = $this->pendingCommand;
        $this->allowDangerous = true;
        $this->runCommand($policy, $terminal);
    }

    public function runQuickCommand(string $command, TerminalCommandPolicy $policy, TerminalService $terminal): void
    {
        $this->command = $command;
        $this->runCommand($policy, $terminal);
    }

    public function runMaintenance(string $name, TerminalCommandPolicy $policy, TerminalService $terminal): void
    {
        $commands = [
            'optimize' => 'php artisan optimize',
            'clear-cache' => 'php artisan optimize:clear',
            'git-status' => 'git status',
            'git-pull' => 'git pull',
            'disk-report' => 'df -h',
        ];

        if (! isset($commands[$name])) return;
        $this->command = $commands[$name];
        $this->background = true;
        $this->runCommand($policy, $terminal);
    }

    public function refreshJob(): void
    {
        if (! $this->activeJobId) return;

        $job = TerminalCommandHistory::where('user_id', auth()->id())->find($this->activeJobId);
        if (! $job) {
            $this->activeJobId = null;
            return;
        }

        $this->jobStatus = $job->status;
        $this->output = $job->output ?? $this->output;
        if ($job->isFinished()) {
            $this->exitCode = $job->exit_code;
            $this->durationMs = $job->duration_ms;
            $this->activeJobId = null;
            $this->loadHistory();
            $this->dispatch('terminal-output', output: $this->output, cwd: $this->cwd, code: $this->exitCode);
        }
    }

    public function cancelJob(): void
    {
        if (! $this->activeJobId) return;
        TerminalCommandHistory::where('user_id', auth()->id())
            ->whereKey($this->activeJobId)
            ->update(['cancel_requested' => true]);
        $this->jobStatus = 'cancelled';
        $this->notice = 'Cancelación solicitada.';
    }

    public function updatedSelectedServerId(): void
    {
        $this->cwd = '/var/www';
    }

    public function loadHistory(): void
    {
        $this->history = TerminalCommandHistory::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(30)
            ->get()
            ->pluck('command')
            ->all();
    }

    protected function isDangerous(string $command): bool
    {
        return (bool) preg_match('/(^|\s)(rm|mv|chmod|chown|apt|apt-get|systemctl|ufw|docker|git\s+pull|git\s+reset)(\s|$)/i', $command);
    }

    public function render()
    {
        $servers = Server::forUser(auth()->id())
            ->orderByDesc('is_local')
            ->orderBy('name')
            ->get(['id', 'name', 'hostname', 'status', 'is_local']);

        return view('livewire.terminal.terminal-index', [
            'servers' => $servers,
            'suggestions' => array_values(array_unique(array_merge(
                array_column($this->quickCommands, 'command'),
                config('larapanel.security.allowed_terminal_commands', [])
            ))),
        ])->layout('layouts.app', [
            'title' => 'Terminal Web',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Terminal</strong>',
        ]);
    }
}
