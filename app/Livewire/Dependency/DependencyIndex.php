<?php

namespace App\Livewire\Dependency;

use App\Services\DependencyMonitorService;
use Livewire\Component;

class DependencyIndex extends Component
{
    public array $selected = [];

    public array $updates = ['count' => 0, 'method' => '', 'message' => '', 'packages' => []];
    public array $security = ['support' => false, 'source' => '', 'message' => '', 'count' => 0, 'packages' => []];
    public array $core = [];

    public ?array $preview = null;
    public string $previewMessage = '';

    public string $successMessage = '';
    public string $errorMessage = '';

    public function mount(DependencyMonitorService $svc): void
    {
        $this->loadData($svc);
    }

    public function loadData(DependencyMonitorService $svc): void
    {
        try {
            $this->updates  = $svc->availableUpdates();
            $this->security = $svc->securityUpdates();
            $this->core     = $svc->coreServiceVersions();
            $this->errorMessage = '';
            $this->successMessage = 'Datos de dependencias actualizados.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al consultar dependencias: ' . $e->getMessage();
        }
    }

    /**
     * Non-destructive upgrade preview: runs `apt-get --simulate upgrade`
     * for the selected packages. Nothing is installed from the panel.
     */
    public function dryRunUpgrade(DependencyMonitorService $svc): void
    {
        if (empty($this->selected)) {
            $this->errorMessage = 'Selecciona al menos un paquete para simular.';
            return;
        }

        try {
            $result = $svc->upgradePackages($this->selected, apply: false);
            $this->preview      = $result['preview'] ?? [];
            $this->previewMessage = $result['message'] ?? '';

            if (($result['ok'] ?? false)) {
                $this->successMessage = 'Simulación completada. Ningún paquete fue modificado.';
                $this->errorMessage = '';
            } else {
                $this->errorMessage = $result['message'] ?? 'Falló la simulación de actualización.';
                $this->successMessage = '';
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al simular la actualización: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $securityNames = collect($this->security['packages'] ?? [])->pluck('name')->all();

        return view('livewire.dependency.dependency-index', [
            'securityNames' => $securityNames,
        ])->layout('layouts.app', [
            'title'      => 'Dependencias y CVEs',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Dependencias</strong>',
            'fluid'      => false,
        ]);
    }
}