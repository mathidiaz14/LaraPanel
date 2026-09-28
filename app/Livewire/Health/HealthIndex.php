<?php

namespace App\Livewire\Health;

use App\Services\HealthScoreService;
use App\Shell\ServerContext;
use Livewire\Component;

class HealthIndex extends Component
{
    public int $hours = 24;

    public array $scoreData = [
        'score' => 0,
        'grade' => 'F',
        'computed_at' => '',
        'components' => [],
    ];

    public array $trend = ['labels' => [], 'cpu' => [], 'ram' => [], 'disk' => []];
    public array $snapshots = [];

    public string $successMessage = '';
    public string $errorMessage = '';

    public function mount(HealthScoreService $svc): void
    {
        $this->load($svc);
    }

    public function load(HealthScoreService $svc): void
    {
        try {
            $server = ServerContext::server();

            $this->scoreData = $svc->score($server);
            $this->trend     = $svc->trend($this->hours);
            $this->snapshots = $svc->latestSnapshots(30);

            $this->errorMessage = '';
            $this->successMessage = 'Salud del servidor actualizada.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al calcular la salud del servidor: ' . $e->getMessage();
        }
    }

    /**
     * Persist a snapshot of the current score (auditable operation).
     */
    public function saveSnapshot(HealthScoreService $svc): void
    {
        try {
            $snapshot = $svc->persistSnapshot(ServerContext::server());
            $this->snapshots = $svc->latestSnapshots(30);
            $this->successMessage = "Snapshot guardado: {$snapshot->score} ({$snapshot->grade}).";
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al guardar el snapshot: ' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.health.health-index')->layout('layouts.app', [
            'title'      => 'Salud del Servidor',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Salud</strong>',
            'fluid'      => false,
        ]);
    }
}