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

    // Edit Domain Logic
    public ?int $editingId = null;
    public string $editPath = '';
    public string $editPhp = '';

    public function editDomain(int $id): void
    {
        $domain = Domain::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->editingId = $domain->id;
        $this->editPath = $domain->document_root;
        $this->editPhp = $domain->php_version;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editPath = '';
        $this->editPhp = '';
    }

    public function updateDomain(DomainService $service): void
    {
        $domain = Domain::where('id', $this->editingId)->where('user_id', auth()->id())->firstOrFail();
        
        $this->validate([
            'editPath' => 'required|string|min:5|max:255',
            'editPhp' => 'required|string',
        ]);

        $domain->update([
            'document_root' => $this->editPath,
            'php_version' => $this->editPhp,
        ]);

        $service->deployConfigs($domain);

        $this->successMessage = "Configuración del dominio {$domain->name} actualizada.";
        $this->cancelEdit();
    }

    public function render(DomainService $service)
    {
        $domains = $this->getDomains();
        
        $grouped = [];
        $domainNames = $domains->pluck('name')->toArray();
        
        foreach ($domains as $domain) {
            $isSubdomain = false;
            foreach ($domainNames as $potentialRoot) {
                if ($domain->name !== $potentialRoot && str_ends_with($domain->name, '.' . $potentialRoot)) {
                    $grouped[$potentialRoot]['subdomains'][] = $domain;
                    $isSubdomain = true;
                    break;
                }
            }
            if (!$isSubdomain) {
                if (!isset($grouped[$domain->name])) {
                    $grouped[$domain->name] = ['main' => $domain, 'subdomains' => []];
                } else {
                    $grouped[$domain->name]['main'] = $domain;
                }
            }
        }

        return view('livewire.domains.domain-index', [
            'domains' => $domains,
            'groupedDomains' => $grouped,
            'phpVersions' => $service->getAvailablePhpVersions(),
        ])->layout('layouts.app', [
            'title'      => 'Dominios',
            'breadcrumb' => '<span>Hosting</span> / <strong>Dominios</strong>',
        ]);
    }
}
