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

    protected MonitoringService $monitoring;

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

    public function loadMetrics(): void
    {
        $this->metrics  = $this->monitoring->snapshot();
        $this->services = $this->monitoring->servicesStatus();
        $this->loading  = false;
    }

    public function loadHistory(): void
    {
        $this->history = ServerMetric::recent(1)
            ->orderBy('recorded_at')
            ->get(['cpu_usage', 'ram_usage', 'disk_usage', 'recorded_at'])
            ->map(fn($m) => [
                'cpu'  => $m->cpu_usage,
                'ram'  => $m->ram_usage,
                'disk' => $m->disk_usage,
                'time' => $m->recorded_at->format('H:i:s'),
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
