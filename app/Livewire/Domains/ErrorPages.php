<?php

namespace App\Livewire\Domains;

use App\Models\Domain;
use App\Services\ErrorPageService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ErrorPages extends Component
{
    public ?int $domainId = null;

    public string $e401 = '';
    public string $e403 = '';
    public string $e404 = '';
    public string $e500 = '';
    public string $e502 = '';
    public string $e503 = '';

    public string $successMessage = '';
    public string $errorMessage   = '';

    protected const CODE_PROPS = [
        401 => 'e401',
        403 => 'e403',
        404 => 'e404',
        500 => 'e500',
        502 => 'e502',
        503 => 'e503',
    ];

    public function mount(int $domainId): void
    {
        $this->domainId = $this->findDomain($domainId)->id;
        $this->loadFromDomain($this->findDomain($this->domainId));
    }

    public function updatedDomainId(): void
    {
        $this->clearMessages();

        if ($this->domainId === null) {
            $this->resetFields();

            return;
        }

        try {
            $this->loadFromDomain($this->findDomain($this->domainId));
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function save(ErrorPageService $service): void
    {
        $this->clearMessages();

        if ($this->domainId === null) {
            $this->errorMessage = 'Selecciona un dominio primero.';

            return;
        }

        try {
            $domain = $this->findDomain($this->domainId);

            $payload = [];
            foreach (self::CODE_PROPS as $code => $prop) {
                $payload[$code] = $this->{$prop};
            }

            $service->save($domain, $payload);
            $this->loadFromDomain($domain);

            $this->successMessage = 'Páginas de error guardadas y configuración desplegada correctamente.';
        } catch (\Throwable $e) {
            Log::error('ErrorPages: save falló', ['domain_id' => $this->domainId, 'error' => $e->getMessage()]);
            $this->errorMessage = 'Error al guardar las páginas de error: ' . $e->getMessage();
        }
    }

    public function clear(ErrorPageService $service): void
    {
        $this->clearMessages();

        if ($this->domainId === null) {
            $this->errorMessage = 'Selecciona un dominio primero.';

            return;
        }

        try {
            $service->clear($this->findDomain($this->domainId));
            $this->resetFields();

            $this->successMessage = 'Páginas de error personalizadas eliminadas.';
        } catch (\Throwable $e) {
            Log::error('ErrorPages: clear falló', ['domain_id' => $this->domainId, 'error' => $e->getMessage()]);
            $this->errorMessage = 'Error al eliminar las páginas de error: ' . $e->getMessage();
        }
    }

    protected function findDomain(int $id): Domain
    {
        return Domain::where('user_id', auth()->id())->findOrFail($id);
    }

    protected function loadFromDomain(Domain $domain): void
    {
        $pages = $domain->error_pages ?? [];

        foreach (self::CODE_PROPS as $code => $prop) {
            $this->{$prop} = (string) ($pages[$code] ?? $pages[(string) $code] ?? '');
        }
    }

    protected function resetFields(): void
    {
        foreach (self::CODE_PROPS as $prop) {
            $this->{$prop} = '';
        }
    }

    protected function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function render()
    {
        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $fields = [
            401 => ['label' => 'No Autorizado',              'prop' => 'e401'],
            403 => ['label' => 'Prohibido',                  'prop' => 'e403'],
            404 => ['label' => 'No Encontrado',              'prop' => 'e404'],
            500 => ['label' => 'Error Interno del Servidor', 'prop' => 'e500'],
            502 => ['label' => 'Bad Gateway',                'prop' => 'e502'],
            503 => ['label' => 'Servicio No Disponible',     'prop' => 'e503'],
        ];

        return view('livewire.domains.error-pages', [
            'domains' => $domains,
            'fields'  => $fields,
        ])->layout('layouts.app', [
            'title'      => 'Páginas de Error',
            'breadcrumb' => '<span><a href="' . route('domains.index') . '">Dominios</a></span> / <strong>Páginas de Error</strong>',
        ]);
    }
}