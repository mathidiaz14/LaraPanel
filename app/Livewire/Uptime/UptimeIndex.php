<?php

namespace App\Livewire\Uptime;

use Livewire\Component;
use App\Models\UptimeMonitor;
use App\Models\UptimePing;
use App\Shell\ServerContext;
use Carbon\Carbon;

class UptimeIndex extends Component
{
    public bool $showCreateModal = false;
    public string $name = '';
    public string $type = 'http';
    public string $target = '';
    public int $interval_minutes = 5;

    public string $successMessage = '';
    public string $errorMessage = '';

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:http,docker',
            'target' => 'required|string',
            'interval_minutes' => 'required|integer|min:1|max:60',
        ];
    }

    public function createMonitor()
    {
        $this->validate();

        try {
            UptimeMonitor::create([
                'user_id' => auth()->id(),
                'server_id' => ServerContext::serverId(),
                'name' => $this->name,
                'type' => $this->type,
                'target' => $this->target,
                'interval_minutes' => $this->interval_minutes,
                'status' => 'pending',
            ]);

            $this->showCreateModal = false;
            $this->reset(['name', 'type', 'target', 'interval_minutes']);
            $this->successMessage = 'Monitor creado correctamente.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al crear el monitor: ' . $e->getMessage();
        }
    }

    public function deleteMonitor(int $id)
    {
        try {
            $monitor = UptimeMonitor::where('user_id', auth()->id())->findOrFail($id);
            $monitor->pings()->delete();
            $monitor->delete();
            $this->successMessage = 'Monitor eliminado correctamente.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al eliminar el monitor: ' . $e->getMessage();
        }
    }

    public function togglePause(int $id)
    {
        try {
            $monitor = UptimeMonitor::where('user_id', auth()->id())->findOrFail($id);
            if ($monitor->status === 'paused') {
                $monitor->status = 'pending'; // Will be checked soon
            } else {
                $monitor->status = 'paused';
            }
            $monitor->save();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al cambiar estado: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $monitors = UptimeMonitor::where('user_id', auth()->id())
            ->where('server_id', ServerContext::serverId())
            ->orderByDesc('created_at')
            ->get();

        // Get 24h stats for each monitor
        $chartData = [];
        $twentyFourHoursAgo = Carbon::now()->subHours(24);

        foreach ($monitors as $monitor) {
            $pings = $monitor->pings()
                ->where('created_at', '>=', $twentyFourHoursAgo)
                ->orderBy('created_at', 'asc')
                ->get();

            $total = $pings->count();
            $up = $pings->where('status', 'up')->count();
            $uptimePercentage = $total > 0 ? round(($up / $total) * 100, 2) : 100;

            $labels = [];
            $data = [];
            
            // To prevent huge arrays, we can resample or just pass them if it's every 5 mins (288 points)
            foreach ($pings as $ping) {
                $labels[] = $ping->created_at->format('H:i');
                // Use 0 if down to show drop in chart
                $data[] = $ping->status === 'up' ? $ping->response_time_ms : 0;
            }

            $chartData[$monitor->id] = [
                'uptime' => $uptimePercentage,
                'labels' => $labels,
                'data' => $data,
            ];
        }

        return view('livewire.uptime.uptime-index', [
            'monitors' => $monitors,
            'chartData' => $chartData
        ])->layout('layouts.app', [
            'title'      => 'Monitor de Servicios',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Monitor</strong>',
            'fluid' => false,
        ]);
    }
}
