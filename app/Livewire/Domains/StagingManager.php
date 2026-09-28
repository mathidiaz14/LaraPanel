<?php

namespace App\Livewire\Domains;

use App\Models\Domain;
use App\Services\ClusterService;
use Livewire\Component;

/**
 * StagingManager — configure load-balanced staging upstreams for a domain
 * and toggle whether it serves traffic through the group (clustering/LB).
 */
class StagingManager extends Component
{
    public int $domainId;
    public string $newAddress = '';
    public string $successMessage = '';
    public string $errorMessage = '';

    public function mount(int $domain): void
    {
        $this->domainId = $domain;
    }

    public function domain(): Domain
    {
        $domain = Domain::findOrFail($this->domainId);
        $this->authorize('manageStaging', $domain);

        return $domain;
    }

    public function addMember(ClusterService $service): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            $service->addMember($this->domain(), $this->newAddress, auth()->user());
            $this->newAddress = '';
            $this->successMessage = 'Nodo añadido al grupo de staging.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function removeMember(ClusterService $service, string $address): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            $service->removeMember($this->domain(), $address, auth()->user());
            $this->successMessage = 'Nodo eliminado del grupo de staging.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleStaging(ClusterService $service): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
        $domain = $this->domain();

        try {
            $service->setStaging($domain, !$domain->onStaging(), auth()->user());
            $this->successMessage = $domain->onStaging()
                ? 'El dominio ahora sirve tráfico a través del grupo de staging.'
                : 'El dominio vuelve a servirse desde el backend local.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(ClusterService $service)
    {
        $domain = $this->domain();

        return view('livewire.domains.staging-manager', [
            'domain'  => $domain,
            'members' => $service->members($domain),
            'onStaging' => $domain->onStaging(),
        ])->layout('layouts.app', [
            'title'      => 'Staging / Balanceo — ' . $domain->name,
            'breadcrumb' => '<span>Dominios</span> / <strong>Staging</strong>',
        ]);
    }
}