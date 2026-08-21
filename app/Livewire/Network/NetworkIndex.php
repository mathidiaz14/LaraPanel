<?php

namespace App\Livewire\Network;

use App\Services\NetworkService;
use Livewire\Component;

class NetworkIndex extends Component
{
    public string $activeTab = 'ports'; // ports | connections
    public string $search    = '';

    public array $ports       = [];
    public array $connections = [];
    public array $summary     = ['listening' => 0, 'established' => 0, 'unique_ips' => 0];

    public string $successMessage = '';
    public string $errorMessage   = '';

    public function mount(NetworkService $network): void
    {
        $this->loadData($network);
    }

    public function loadData(?NetworkService $network = null): void
    {
        $network ??= app(NetworkService::class);

        try {
            $this->ports       = $network->getListeningPorts();
            $this->connections = $network->getEstablished();
            $this->summary     = [
                'listening'   => count($this->ports),
                'established' => count($this->connections),
                'unique_ips'  => collect($this->connections)
                    ->map(fn($c) => explode(':', $c['peer'])[0] ?? '')
                    ->filter(fn($ip) => $ip !== '' && $ip !== '*')
                    ->unique()
                    ->count(),
            ];
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = 'No se pudo consultar las conexiones de red: ' . $e->getMessage();
        }
    }

    public function getFilteredPortsProperty(): array
    {
        return $this->applySearch($this->ports);
    }

    public function getFilteredConnectionsProperty(): array
    {
        return $this->applySearch($this->connections);
    }

    protected function applySearch(array $rows): array
    {
        $search = mb_strtolower(trim($this->search));
        if ($search === '') return $rows;

        return array_values(array_filter($rows, fn($r) =>
            str_contains(mb_strtolower((string)$r['process']), $search) ||
            str_contains(mb_strtolower($r['local_addr']), $search) ||
            str_contains(mb_strtolower($r['peer']), $search) ||
            (string)$r['local_port'] === $search
        ));
    }

    private function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function render()
    {
        return view('livewire.network.network-index')
            ->layout('layouts.app', [
                'title'      => 'Red',
                'breadcrumb' => '<span>Sistema</span> / <strong>Red</strong>',
            ]);
    }
}
