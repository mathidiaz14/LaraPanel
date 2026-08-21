<?php

namespace App\Livewire\Processes;

use App\Services\MonitoringService;
use Livewire\Component;

class ProcessesIndex extends Component
{
    public string $sortBy = 'mem'; // mem | cpu
    public string $search = '';
    public bool $autoRefresh = true;
    public int $visibleRows = 50;

    public array $processes = [];
    public array $summary = [];

    // Kill confirmation modal
    public ?int $confirmKillPid = null;
    public string $confirmKillCmd = '';
    public bool $forceKill = false;

    public string $successMessage = '';
    public string $errorMessage   = '';

    public function mount(MonitoringService $monitoring): void
    {
        $this->refreshProcesses($monitoring);
    }

    public function refreshProcesses(MonitoringService $monitoring): void
    {
        try {
            $this->processes = $monitoring->getProcessList();
            $this->summary   = [
                'ram'  => $monitoring->getRamMetrics(),
                'load' => $monitoring->getLoadAverage(),
            ];
        } catch (\Throwable $e) {
            $this->errorMessage = 'No se pudo obtener la lista de procesos: ' . $e->getMessage();
        }
    }

    /**
     * Filtered, sorted and limited view over the cached process list.
     */
    public function getFilteredProcessesProperty(): array
    {
        $search = mb_strtolower(trim($this->search));

        $rows = collect($this->processes)
            ->when($search !== '', fn($c) => $c->filter(fn($p) =>
                str_contains(mb_strtolower($p['user']), $search) ||
                str_contains(mb_strtolower($p['command']), $search) ||
                str_contains(mb_strtolower($p['full_cmd']), $search) ||
                (string)$p['pid'] === $search
            ));

        if ($this->sortBy === 'cpu') {
            $rows = $rows->sortByDesc('cpu');
        } else {
            $rows = $rows->sortByDesc('rss_bytes');
        }

        return $rows->take($this->visibleRows)->values()->all();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function resetPage(): void
    {
        // Reserved for future pagination
    }

    public function confirmKill(int $pid, string $cmd = ''): void
    {
        if ($pid <= 1) {
            $this->errorMessage = 'No se permite terminar el proceso init (PID 1).';
            return;
        }
        $this->confirmKillPid = $pid;
        $this->confirmKillCmd = $cmd;
        $this->forceKill      = false;
    }

    public function cancelKill(): void
    {
        $this->confirmKillPid = null;
        $this->confirmKillCmd = '';
        $this->forceKill      = false;
    }

    public function killProcess(MonitoringService $monitoring): void
    {
        $this->clearMessages();

        if ($this->confirmKillPid === null) return;

        try {
            $ok = $monitoring->killProcess($this->confirmKillPid, $this->forceKill);
            if ($ok) {
                $signal = $this->forceKill ? 'SIGKILL' : 'SIGTERM';
                $this->successMessage = "Señal {$signal} enviada al proceso #{$this->confirmKillPid}.";
            } else {
                $this->errorMessage = "No se pudo terminar el proceso #{$this->confirmKillPid}. Verifica que exista y tengas permisos.";
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error: ' . $e->getMessage();
        }

        $this->cancelKill();
        $this->refreshProcesses(app(MonitoringService::class));
    }

    private function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function render()
    {
        return view('livewire.processes.processes-index')
            ->layout('layouts.app', [
                'title'      => 'Procesos',
                'breadcrumb' => '<span>Sistema</span> / <strong>Procesos</strong>',
            ]);
    }
}
