<?php

namespace App\Livewire;

use App\Services\MonitoringService;
use App\Models\Domain;
use App\Models\ServerMetric;
use Livewire\Component;
use Livewire\Attributes\On;

class Dashboard extends Component
{
    public array $metrics = [];
    public array $services = [];
    public array $history = [];
    public int $domainCount = 0;
    public bool $loading = true;

    public string $timeRange = '1h'; // 1h, 24h, 7d

    public function boot(MonitoringService $monitoring): void
    {
        $this->monitoring = $monitoring;
    }

    public function mount(): void
    {
        $this->loadMetrics();
        $this->loadHistory();
        $this->domainCount = Domain::where('user_id', auth()->id())->active()->count();
    }

    public function updatedTimeRange()
    {
        $this->loadHistory();
        $this->dispatch('history-updated', $this->history);
    }

    public function loadMetrics(): void
    {
        $this->metrics  = $this->monitoring->snapshot();
        $this->services = $this->monitoring->servicesStatus();
        $this->loading  = false;
    }

    public function loadHistory(): void
    {
        $hours = match($this->timeRange) {
            '24h' => 24,
            '7d'  => 168,
            default => 1,
        };

        $query = ServerMetric::recent($hours)->orderBy('recorded_at');

        // Para evitar demasiados puntos en 24h/7d, podríamos agrupar, pero por ahora tomamos limit si es muy grande, o raw.
        // Dado que corre cada 5 minutos:
        // 1h = 12 puntos
        // 24h = 288 puntos
        // 7d = 2016 puntos
        // Lo devolvemos entero, Chart.js puede manejar 2000 puntos bien con decimation, o podemos tomar 1 de cada X.

        $this->history = $query->get(['cpu_usage', 'ram_usage', 'disk_usage', 'recorded_at'])
            ->map(fn($m) => [
                'cpu'  => $m->cpu_usage,
                'ram'  => $m->ram_usage,
                'disk' => $m->disk_usage,
                'time' => $m->recorded_at->format('H:i'), // Formato corto
            ])
            ->values()
            ->toArray();
    }

    // Called by JS polling every 5 seconds
    #[On('refresh-metrics')]
    public function refreshMetrics(): void
    {
        $this->loadMetrics();
        $this->dispatch('snapshot-updated', $this->metrics);
    }

    public function render()
    {
        return view('livewire.dashboard')
            ->layout('layouts.app', [
                'title'      => 'Dashboard',
                'breadcrumb' => '<strong>Dashboard</strong>',
            ]);
    }
}
