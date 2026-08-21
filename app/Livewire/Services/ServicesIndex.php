<?php

namespace App\Livewire\Services;

use App\Services\ServiceManagerService;
use Livewire\Component;

class ServicesIndex extends Component
{
    public array $services = [];
    public array $summary  = ['active' => 0, 'total' => 0, 'critical_down' => 0];

    // Pending action confirmation modal
    public ?string $pendingService = null;
    public ?string $pendingAction  = null;
    public ?string $pendingLabel   = null;
    public bool $pendingCritical   = false;

    public string $successMessage = '';
    public string $errorMessage   = '';

    public function mount(ServiceManagerService $manager): void
    {
        $this->loadServices($manager);
    }

    public function loadServices(?ServiceManagerService $manager = null): void
    {
        $manager ??= app(ServiceManagerService::class);

        try {
            $this->services = $manager->listServices();
            $active         = collect($this->services)->where('active', true)->count();

            $this->summary = [
                'active'        => $active,
                'total'         => count($this->services),
                'critical_down' => collect($this->services)
                    ->where('critical', true)
                    ->where('active', false)
                    ->count(),
            ];
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = 'No se pudo consultar el estado de los servicios: ' . $e->getMessage();
        }
    }

    /**
     * Open the confirmation modal for a destructive action.
     */
    public function askAction(string $service, string $label, string $action, bool $critical): void
    {
        if (!in_array($action, ServiceManagerService::ACTIONS, true)) return;

        if ($action === 'start') {
            // Starting is non-destructive: run directly
            $this->runAction(service: $service, label: $label, action: 'start');
            return;
        }

        $this->pendingService  = $service;
        $this->pendingLabel    = $label;
        $this->pendingAction   = $action;
        $this->pendingCritical = $critical;
    }

    public function cancelAction(): void
    {
        $this->pendingService  = null;
        $this->pendingAction   = null;
        $this->pendingLabel    = null;
        $this->pendingCritical = false;
    }

    public function runConfirmed(): void
    {
        if ($this->pendingService === null || $this->pendingAction === null) return;

        $service = $this->pendingService;
        $label   = $this->pendingLabel ?? $service;
        $action  = $this->pendingAction;

        $this->cancelAction();
        $this->runAction($service, $label, $action);
    }

    protected function runAction(string $service, string $label, string $action): void
    {
        $this->clearMessages();

        try {
            app(ServiceManagerService::class)->performAction($service, $action);

            $verbs = ['start' => 'iniciado', 'stop' => 'detenido', 'restart' => 'reiniciado'];
            $this->successMessage = "{$label} {$verbs[$action]} correctamente.";

            sleep(1); // give systemd a moment before re-checking status
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al {$action} {$label}: " . $e->getMessage();
        }

        $this->loadServices(app(ServiceManagerService::class));
    }

    private function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function render()
    {
        return view('livewire.services.services-index')
            ->layout('layouts.app', [
                'title'      => 'Servicios',
                'breadcrumb' => '<span>Sistema</span> / <strong>Servicios</strong>',
            ]);
    }
}
