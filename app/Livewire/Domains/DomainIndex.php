<?php

namespace App\Livewire\Domains;

use App\Models\AuditLog;
use App\Models\Domain;
use App\Services\DomainService;
use App\Services\SslService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\WithPagination;

class DomainIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter = 'all';
    public string $typeFilter = 'all';
    public string $sslFilter = 'all';
    public string $sortBy = 'created';
    public string $sortDir = 'desc';
    public int $perPage = 10;
    public array $selected = [];
    public array $visibleDomainIds = [];
    public ?int $deletingId = null;
    public bool $deleteFiles = false;
    public string $successMessage = '';
    public string $errorMessage = '';
    public ?int $configDomainId = null;
    public string $configContent = '';
    public ?int $healthDomainId = null;
    public array $healthResult = [];
    public ?int $editingId = null;
    public string $editPath = '';
    public string $editPhp = '';

    protected array $rules = [
        'editPath' => 'required|string|min:5|max:255',
        'editPhp'  => 'required|string',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSslFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortByColumn(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = $column === 'name' ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter', 'typeFilter', 'sslFilter', 'sortBy', 'sortDir', 'perPage']);
        $this->sortBy = 'created';
        $this->sortDir = 'desc';
        $this->perPage = 10;
        $this->resetPage();
    }

    public function toggleVisibleSelection(): void
    {
        $visible = array_map('intval', $this->visibleDomainIds);
        $selected = array_map('intval', $this->selected);

        $this->selected = count(array_intersect($selected, $visible)) === count($visible) && $visible !== []
            ? []
            : $visible;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function confirmDelete(int $id): void
    {
        $this->ownedDomain($id);
        $this->deletingId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
        $this->deleteFiles = false;
    }

    public function deleteDomain(DomainService $service): void
    {
        $domain = $this->ownedDomain($this->deletingId);
        $this->authorize('delete', $domain);

        $service->delete($domain, $this->deleteFiles);

        $this->selected = array_values(array_diff(array_map('intval', $this->selected), [$domain->id]));
        $this->deletingId = null;
        $this->deleteFiles = false;
        $this->successMessage = "Dominio {$domain->name} eliminado correctamente.";
    }

    public function suspendDomain(int $id, DomainService $service): void
    {
        $domain = $this->ownedDomain($id);
        $this->authorize('update', $domain);

        $service->suspend($domain, 'Manual suspension from panel');
        $this->successMessage = "Dominio {$domain->name} suspendido.";
    }

    public function unsuspendDomain(int $id, DomainService $service): void
    {
        $domain = $this->ownedDomain($id);
        $this->authorize('update', $domain);

        $service->unsuspend($domain);
        $this->successMessage = "Dominio {$domain->name} reactivado.";
    }

    public function editDomain(int $id): void
    {
        $domain = $this->ownedDomain($id);
        $this->authorize('update', $domain);

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
        $domain = $this->ownedDomain($this->editingId);
        $this->authorize('update', $domain);

        $this->validate();

        $domain->update([
            'document_root' => $this->editPath,
            'php_version' => $this->editPhp,
        ]);

        $service->deployConfigs($domain);
        AuditLog::record('domain.updated', $domain->name, [
            'document_root' => $this->editPath,
            'php_version' => $this->editPhp,
        ]);

        $this->successMessage = "Configuración del dominio {$domain->name} actualizada.";
        $this->cancelEdit();
    }

    public function showConfig(int $id, DomainService $service): void
    {
        $domain = $this->ownedDomain($id);
        $this->authorize('update', $domain);

        $this->configDomainId = $domain->id;
        $this->configContent = (string) ($domain->config['nginx'] ?? $service->generateNginxConfig($domain));
        AuditLog::record('domain.config.viewed', $domain->name);
    }

    public function closeConfig(): void
    {
        $this->configDomainId = null;
        $this->configContent = '';
    }

    public function deployDomain(int $id, DomainService $service): void
    {
        $domain = $this->ownedDomain($id);
        $this->authorize('update', $domain);

        $service->deployConfigs($domain);
        AuditLog::record('domain.config.deployed', $domain->name);

        $this->successMessage = "Configuración de {$domain->name} desplegada correctamente.";
    }

    public function renewDomainSsl(int $id, SslService $service): void
    {
        $domain = $this->ownedDomain($id);
        $this->authorize('update', $domain);

        if (!$domain->ssl_enabled) {
            $this->errorMessage = "El dominio {$domain->name} no tiene SSL instalado.";
            return;
        }

        try {
            $service->renewCertificate($domain);
            $this->successMessage = "Certificado de {$domain->name} renovado correctamente.";
        } catch (\Throwable $e) {
            $this->errorMessage = "No se pudo renovar el certificado de {$domain->name}: {$e->getMessage()}";
        }
    }

    public function checkDomain(int $id): void
    {
        $domain = $this->ownedDomain($id);
        $this->healthDomainId = $domain->id;

        if (!$this->isSafeHost($domain->name)) {
            $this->healthResult = ['ok' => false, 'error' => 'Nombre de dominio no válido para una comprobación HTTP.'];
            return;
        }

        $scheme = $domain->ssl_enabled ? 'https' : 'http';
        $url = $scheme . '://' . $domain->name;

        try {
            $response = Http::timeout(8)->withoutVerifying()->get($url);
            $status = $response->status();

            $this->healthResult = [
                'ok' => $response->successful() || in_array($status, [401, 403], true),
                'status' => $status,
                'url' => $url,
                'finalUrl' => (string) $response->effectiveUri(),
                'redirected' => (string) $response->effectiveUri() !== $url,
                'tlsDays' => $domain->sslExpiresInDays(),
            ];
        } catch (\Throwable $e) {
            $this->healthResult = ['ok' => false, 'error' => $e->getMessage(), 'url' => $url];
        }
    }

    public function closeHealth(): void
    {
        $this->healthDomainId = null;
        $this->healthResult = [];
    }

    public function bulkSuspend(DomainService $service): void
    {
        $domains = $this->selectedDomains();

        if ($domains->isEmpty()) {
            $this->errorMessage = 'Selecciona al menos un dominio.';
            return;
        }

        foreach ($domains as $domain) {
            $this->authorize('update', $domain);
            $service->suspend($domain, 'Bulk suspension from panel');
        }

        AuditLog::record('domain.bulk.suspended', '', ['count' => $domains->count(), 'domains' => $domains->pluck('name')->all()]);
        $this->successMessage = $domains->count() . ' dominio(s) suspendido(s).';
        $this->clearSelection();
    }

    public function bulkUnsuspend(DomainService $service): void
    {
        $domains = $this->selectedDomains();

        if ($domains->isEmpty()) {
            $this->errorMessage = 'Selecciona al menos un dominio.';
            return;
        }

        foreach ($domains as $domain) {
            $this->authorize('update', $domain);
            $service->unsuspend($domain);
        }

        AuditLog::record('domain.bulk.reactivated', '', ['count' => $domains->count(), 'domains' => $domains->pluck('name')->all()]);
        $this->successMessage = $domains->count() . ' dominio(s) reactivado(s).';
        $this->clearSelection();
    }

    public function bulkDeploy(DomainService $service): void
    {
        $domains = $this->selectedDomains();

        if ($domains->isEmpty()) {
            $this->errorMessage = 'Selecciona al menos un dominio.';
            return;
        }

        foreach ($domains as $domain) {
            $this->authorize('update', $domain);
            $service->deployConfigs($domain);
        }

        AuditLog::record('domain.bulk.deployed', '', ['count' => $domains->count(), 'domains' => $domains->pluck('name')->all()]);
        $this->successMessage = $domains->count() . ' configuración(es) desplegada(s).';
        $this->clearSelection();
    }

    public function bulkRenewSsl(SslService $service): void
    {
        $domains = $this->selectedDomains()->filter(fn(Domain $domain) => $domain->ssl_enabled);

        if ($domains->isEmpty()) {
            $this->errorMessage = 'Ningún dominio seleccionado tiene SSL instalado.';
            return;
        }

        $renewed = 0;
        $failed = 0;

        foreach ($domains as $domain) {
            try {
                $this->authorize('update', $domain);
                $service->renewCertificate($domain);
                $renewed++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        AuditLog::record('domain.bulk.ssl_renewed', '', ['renewed' => $renewed, 'failed' => $failed]);
        $this->successMessage = "{$renewed} certificado(s) renovado(s).";

        if ($failed > 0) {
            $this->errorMessage = "{$failed} certificado(s) no se pudieron renovar.";
        }

        $this->clearSelection();
    }

    public function exportCsv()
    {
        $domains = $this->selectedDomains();

        if ($domains->isEmpty()) {
            $this->errorMessage = 'Selecciona al menos un dominio para exportar.';
            return null;
        }

        AuditLog::record('domain.exported', '', ['count' => $domains->count()]);

        return response()->streamDownload(function () use ($domains): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Dominio', 'Tipo', 'Estado', 'PHP', 'Document Root', 'SSL', 'SSL expira', 'Staging', 'Proxy', 'Creado']);

            foreach ($domains as $domain) {
                fputcsv($handle, [
                    $this->csvValue($domain->name),
                    $this->csvValue($domain->type),
                    $this->csvValue($domain->status),
                    $this->csvValue($domain->php_version),
                    $this->csvValue($domain->document_root),
                    $domain->ssl_enabled ? 'si' : 'no',
                    $domain->ssl_expires_at?->toDateString() ?? '',
                    $domain->onStaging() ? 'si' : 'no',
                    $this->csvValue((string) ($domain->getProxyPort() ?? '')),
                    $domain->created_at?->toDateTimeString() ?? '',
                ]);
            }

            fclose($handle);
        }, 'dominios-' . date('Y-m-d-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function domainHealth(Domain $domain): array
    {
        if ($domain->status === 'suspended') {
            return ['level' => 'danger', 'label' => 'Suspendido'];
        }

        if ($domain->status === 'error') {
            return ['level' => 'danger', 'label' => 'Error'];
        }

        if ($domain->status === 'pending') {
            return ['level' => 'warning', 'label' => 'Pendiente'];
        }

        $days = $domain->sslExpiresInDays();

        if ($domain->ssl_enabled && $days !== null && $days <= 0) {
            return ['level' => 'danger', 'label' => 'SSL caducado'];
        }

        if ($domain->ssl_enabled && $days !== null && $days <= 14) {
            return ['level' => 'warning', 'label' => "SSL expira en {$days} d"];
        }

        if (!$domain->ssl_enabled) {
            return ['level' => 'warning', 'label' => 'Sin SSL'];
        }

        if ($domain->onStaging()) {
            return ['level' => 'accent', 'label' => 'Staging'];
        }

        if ($domain->getProxyPort()) {
            return ['level' => 'accent', 'label' => 'Proxy :' . $domain->getProxyPort()];
        }

        return ['level' => 'success', 'label' => 'OK'];
    }

    public function render(DomainService $service)
    {
        $all = Domain::forUser(auth()->id())->get();

        $stats = [
            'total' => $all->count(),
            'active' => $all->where('status', 'active')->count(),
            'suspended' => $all->where('status', 'suspended')->count(),
            'ssl' => $all->where('ssl_enabled', true)->count(),
            'expiring' => $all->filter(fn(Domain $domain) => $domain->sslIsExpiringSoon())->count(),
            'subdomains' => $all->where('type', 'subdomain')->count(),
            'proxies' => $all->where('type', 'proxy')->count(),
        ];

        $domains = Domain::forUser(auth()->id())
            ->when($this->search, fn($query) => $query->where(
                fn($query) => $query
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('document_root', 'like', "%{$this->search}%")
            ))
            ->when($this->filter === 'active', fn($query) => $query->where('status', 'active'))
            ->when($this->filter === 'suspended', fn($query) => $query->where('status', 'suspended'))
            ->when($this->filter === 'error', fn($query) => $query->where('status', 'error'))
            ->when($this->typeFilter !== 'all', fn($query) => $query->where('type', $this->typeFilter))
            ->when($this->sslFilter === 'with', fn($query) => $query->where('ssl_enabled', true))
            ->when($this->sslFilter === 'without', fn($query) => $query->where('ssl_enabled', false))
            ->when($this->sslFilter === 'expiring', fn($query) => $query
                ->where('ssl_enabled', true)
                ->whereNotNull('ssl_expires_at')
                ->where('ssl_expires_at', '<=', now()->addDays(14)))
            ->get();

        $sortColumn = match ($this->sortBy) {
            'name' => 'name',
            'status' => 'status',
            'ssl' => 'ssl_expires_at',
            default => 'created_at',
        };

        $domains = $domains->sortBy($sortColumn, SORT_NATURAL, $this->sortDir === 'desc')->values();

        $groups = $this->groupDomains($domains, $all);
        $paginator = new LengthAwarePaginator(
            $groups->forPage($this->getPage(), $this->perPage)->all(),
            $groups->count(),
            $this->perPage,
            $this->getPage(),
            ['path' => request()->url()],
        );

        $this->visibleDomainIds = collect($paginator->items())
            ->flatMap(fn(array $group) => array_merge(
                $group['mainVisible'] && $group['main'] ? [$group['main']->id] : [],
                $group['subdomains']->pluck('id')->all(),
            ))
            ->unique()
            ->values()
            ->all();

        return view('livewire.domains.domain-index', [
            'domains' => $domains,
            'allDomains' => $all,
            'groups' => $paginator,
            'stats' => $stats,
            'health' => $all->mapWithKeys(fn(Domain $domain) => [$domain->id => $this->domainHealth($domain)])->all(),
            'phpVersions' => $service->getAvailablePhpVersions(),
        ])->layout('layouts.app', [
            'title' => 'Dominios',
            'breadcrumb' => '<span>Hosting</span> / <strong>Dominios</strong>',
        ]);
    }

    protected function groupDomains(Collection $domains, Collection $all): Collection
    {
        $groups = [];

        foreach ($domains as $domain) {
            $rootName = $this->resolveRootName($domain, $all);
            $root = $all->firstWhere('name', $rootName);

            if (!isset($groups[$rootName])) {
                $groups[$rootName] = [
                    'main' => $root,
                    'mainVisible' => false,
                    'subdomains' => collect(),
                ];
            }

            if ($domain->name === $rootName || $domain->isMain()) {
                $groups[$rootName]['main'] = $domain;
                $groups[$rootName]['mainVisible'] = true;
            } else {
                $groups[$rootName]['subdomains']->push($domain);
            }
        }

        return collect($groups)
            ->filter(fn(array $group) => $group['main'] !== null || $group['subdomains']->isNotEmpty())
            ->sortKeys();
    }

    protected function resolveRootName(Domain $domain, Collection $all): string
    {
        if ($domain->parent_domain) {
            $parent = $all->firstWhere('name', $domain->parent_domain);
            if ($parent) {
                return $parent->name;
            }
        }

        if (!str_contains($domain->name, '.')) {
            return $domain->name;
        }

        return $all
            ->filter(fn(Domain $candidate) => $candidate->name !== $domain->name && str_ends_with($domain->name, '.' . $candidate->name))
            ->sortByDesc(fn(Domain $candidate) => strlen($candidate->name))
            ->first()
            ?->name ?? $domain->name;
    }

    protected function ownedDomain(?int $id): Domain
    {
        return Domain::forUser(auth()->id())->findOrFail($id);
    }

    protected function selectedDomains(): Collection
    {
        return Domain::forUser(auth()->id())
            ->whereIn('id', array_map('intval', $this->selected))
            ->get();
    }

    protected function isSafeHost(string $host): bool
    {
        $host = strtolower(trim($host));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false && str_contains($host, '.');
    }

    protected function csvValue(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    }
}
