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
        'name'          => ['required', 'string', 'min:4', 'max:253', 'regex:/^[a-zA-Z0-9\-\.]+$/'],
        'type'          => 'required|in:main,subdomain,addon,parked',
        'parent_domain' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9\-\.]+$/'],
        'php_version'   => ['required', 'string', 'regex:/^[0-9\.]+$/'],
        'webserver'     => 'required|in:nginx,apache,both',
        'document_root' => ['nullable', 'string', 'regex:/^\/[a-zA-Z0-9\-\.\/_]+$/'],
    ];

    public function mount(): void
    {
        $this->php_version = config('larapanel.server.default_php', '8.3');
        $this->webserver   = config('larapanel.server.webserver', 'nginx');
    }

    public function updatedName(): void
    {
        $this->updateDocumentRoot();
    }

    public function updatedParentDomain(): void
    {
        $this->updateDocumentRoot();
    }

    public function updatedType(): void
    {
        $this->updateDocumentRoot();
    }

    protected function updateDocumentRoot(): void
    {
        if ($this->autoRoot) {
            $finalName = strtolower(trim($this->name));
            if ($this->type === 'subdomain' && $this->parent_domain) {
                if (!str_ends_with($finalName, '.' . $this->parent_domain)) {
                    $finalName .= '.' . $this->parent_domain;
                }
            }
            
            if ($finalName) {
                $this->document_root = config('larapanel.paths.webroots', '/var/www')
                    . '/' . $finalName . '/public_html';
            } else {
                $this->document_root = '';
            }
        }
    }

    public function save(DomainService $service): void
    {
        $this->validate();

        $this->errorMessage = '';

        $finalName = strtolower(trim($this->name));
        if ($this->type === 'subdomain' && $this->parent_domain) {
            if (!str_ends_with($finalName, '.' . $this->parent_domain)) {
                $finalName .= '.' . $this->parent_domain;
            }
        }

        // Validate domain name format
        if (!$service->validateDomainName($finalName)) {
            $this->errorMessage = 'El nombre del dominio no es válido. Ej: ejemplo.com';
            return;
        }

        // Check if domain already exists
        if (Domain::where('name', $finalName)->exists()) {
            $this->errorMessage = "El dominio {$finalName} ya está registrado en este servidor.";
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
                'name'          => $finalName,
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
