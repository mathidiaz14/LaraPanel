<?php

namespace App\Livewire\Domains;

use App\Models\Domain;
use App\Services\DomainService;
use Livewire\Component;
use Livewire\Attributes\On;

class DomainIndex extends Component
{
    public string $search = '';
    public string $filter = 'all'; // all | active | suspended
    public ?int $deletingId = null;
    public bool $deleteFiles = false;
    public string $successMessage = '';

    public function getDomains()
    {
        return Domain::forUser(auth()->id())
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filter === 'active',    fn($q) => $q->where('status', 'active'))
            ->when($this->filter === 'suspended', fn($q) => $q->where('status', 'suspended'))
            ->orderByDesc('created_at')
            ->get();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
        $this->deleteFiles = false;
    }

    public function deleteDomain(DomainService $service): void
    {
        $domain = Domain::where('id', $this->deletingId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $service->delete($domain, $this->deleteFiles);

        $this->deletingId = null;
        $this->deleteFiles = false;
        $this->successMessage = "Dominio {$domain->name} eliminado correctamente.";
    }

    public function suspendDomain(int $id, DomainService $service): void
    {
        $domain = Domain::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $service->suspend($domain, 'Manual suspension from panel');
        $this->successMessage = "Dominio {$domain->name} suspendido.";
    }

    public function unsuspendDomain(int $id, DomainService $service): void
    {
        $domain = Domain::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $service->unsuspend($domain);
        $this->successMessage = "Dominio {$domain->name} reactivado.";
    }

    public function render()
    {
        return view('livewire.domains.domain-index', [
            'domains' => $this->getDomains(),
        ])->layout('layouts.app', [
            'title'      => 'Dominios',
            'breadcrumb' => '<span>Hosting</span> / <strong>Dominios</strong>',
        ]);
    }
}
