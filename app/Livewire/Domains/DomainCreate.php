<?php

namespace App\Livewire\Domains;

use App\Models\Domain;
use App\Services\DomainService;
use Livewire\Component;

class DomainCreate extends Component
{
    public string $name         = '';
    public string $type         = 'main';
    public string $parent_domain= '';
    public string $php_version  = '';
    public string $webserver    = 'nginx';
    public string $document_root= '';
    public bool   $autoRoot     = true;
    public bool   $isLoading    = false;
    public string $errorMessage = '';

    protected array $rules = [
        'name'          => 'required|string|min:4|max:253',
        'type'          => 'required|in:main,subdomain,addon,parked',
        'parent_domain' => 'nullable|string',
        'php_version'   => 'required|string',
        'webserver'     => 'required|in:nginx,apache,both',
        'document_root' => 'nullable|string',
    ];

    public function mount(): void
    {
        $this->php_version = config('larapanel.server.default_php', '8.3');
        $this->webserver   = config('larapanel.server.webserver', 'nginx');
    }

    public function updatedName(): void
    {
        if ($this->autoRoot) {
            $this->document_root = config('larapanel.paths.webroots', '/var/www')
                . '/' . strtolower(trim($this->name)) . '/public_html';
        }
    }

    public function save(DomainService $service): void
    {
        $this->validate();

        $this->errorMessage = '';

        // Validate domain name format
        if (!$service->validateDomainName($this->name)) {
            $this->errorMessage = 'El nombre del dominio no es válido. Ej: ejemplo.com';
            return;
        }

        // Check if domain already exists
        if (Domain::where('name', strtolower($this->name))->exists()) {
            $this->errorMessage = "El dominio {$this->name} ya está registrado en este servidor.";
            return;
        }

        // Check plan quota
        if (!auth()->user()->canAddDomain()) {
            $this->errorMessage = 'Has alcanzado el límite de dominios de tu plan.';
            return;
        }

        $this->isLoading = true;

        try {
            $domain = $service->create(auth()->user(), [
                'name'          => $this->name,
                'type'          => $this->type,
                'parent_domain' => $this->parent_domain ?: null,
                'php_version'   => $this->php_version,
                'webserver'     => $this->webserver,
                'document_root' => $this->document_root,
            ]);

            $this->redirect(route('domains.index'), navigate: true);

        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al crear el dominio: ' . $e->getMessage();
            \Log::error('Domain creation failed', ['error' => $e->getMessage()]);
        } finally {
            $this->isLoading = false;
        }
    }

    public function render(DomainService $service)
    {
        $existingDomains = \App\Models\Domain::where('user_id', auth()->id())
            ->where('type', 'main')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.domains.domain-create', [
            'phpVersions'    => $service->getAvailablePhpVersions(),
            'existingDomains'=> $existingDomains,
        ])->layout('layouts.app', [
            'title'      => 'Nuevo Dominio',
            'breadcrumb' => '<span>Dominios</span> / <strong>Nuevo Dominio</strong>',
        ]);
    }
}
