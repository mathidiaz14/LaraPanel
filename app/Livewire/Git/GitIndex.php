<?php

namespace App\Livewire\Git;

use App\Models\AuditLog;
use App\Models\Domain;
use App\Models\GitDeployment;
use App\Models\GitDeploymentLog;
use App\Services\GitDeployDispatcher;
use App\Services\GitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\WithPagination;

class GitIndex extends Component
{
    use WithPagination;

    public $deployments;
    public ?GitDeployment $selectedDeployment = null;
    public ?GitDeploymentLog $selectedLog = null;
    public bool $showLogModal = false;
    public string $activeTab = 'config';
    public array $repoStatus = [];
    public ?array $webhookTestResult = null;
    public array $stats = [];

    public bool $isCreating = false;
    public string $domain_name = '';
    public string $deploy_path = '';
    public string $repository_url = '';
    public string $branch = 'main';
    public string $deploy_script = "composer install --no-interaction --prefer-dist --optimize-autoloader\nphp artisan migrate --force\nphp artisan config:cache\nnpm run build";
    public bool $auto_deploy = true;

    public string $logSearch = '';
    public string $logStatus = 'all';
    public string $logTrigger = 'all';
    public int $perPage = 10;
    public bool $isDeploying = false;
    public ?int $deployStartedAt = null;

    protected function rules(): array
    {
        return [
            'domain_name'    => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\-\.]+$/'],
            'deploy_path'    => ['nullable', 'string', 'max:255', 'regex:/^\/[a-zA-Z0-9\-\.\/_]+$/', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && str_contains((string) $value, '..')) {
                    $fail('La ruta de despliegue no es válida.');
                }
            }],
            'repository_url' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! $this->isValidRepositoryUrl((string) $value)) {
                    $fail('La URL del repositorio debe ser HTTPS, SSH (git@host:ruta) o ssh://.');
                }
            }],
            'branch'         => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\-\.\/_]+$/'],
            'deploy_script'  => 'nullable|string',
            'auto_deploy'    => 'boolean',
        ];
    }

    public function mount()
    {
        abort_unless(auth()->user(), 403);
        $this->loadDeployments();
    }

    public function updatedDomainName($value)
    {
        if (! $value) {
            return;
        }

        $domain = Domain::where('user_id', auth()->id())
            ->where('name', $value)
            ->first();

        $this->deploy_path = $domain?->document_root ?: "/var/www/{$value}/public_html";
    }

    public function updatingLogSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLogStatus(): void
    {
        $this->resetPage();
    }

    public function updatingLogTrigger(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function resetLogFilters(): void
    {
        $this->reset(['logSearch', 'logStatus', 'logTrigger', 'perPage']);
        $this->perPage = 10;
        $this->resetPage();
    }

    public function loadDeployments(): void
    {
        $this->deployments = GitDeployment::where('user_id', auth()->id())
            ->latest('last_deployed_at')
            ->get();

        $this->computeStats();

        if (! $this->selectedDeployment && $this->deployments->isNotEmpty()) {
            $this->selectDeployment($this->deployments->first()->id);
        }
    }

    protected function computeStats(): void
    {
        $deployments = $this->deployments ?: collect();
        $ids = $deployments->pluck('id')->all();

        $recentLogs = $ids === []
            ? collect()
            : GitDeploymentLog::whereIn('git_deployment_id', $ids)
                ->where('created_at', '>=', now()->subDays(30))
                ->get(['status']);

        $success = $recentLogs->where('status', 'success')->count();
        $failed = $recentLogs->where('status', 'failed')->count();
        $total = $success + $failed;

        $lastDeployed = $deployments
            ->pluck('last_deployed_at')
            ->filter()
            ->sortDesc()
            ->first();

        $this->stats = [
            'repositories' => $deployments->count(),
            'auto_deploy' => $deployments->where('auto_deploy', true)->count(),
            'success_rate' => $total > 0 ? (int) round($success / $total * 100) : 0,
            'last_deploy' => $lastDeployed ? Carbon::parse($lastDeployed)->diffForHumans() : 'Sin despliegues',
        ];
    }

    protected function ownedDeployment(int $id): ?GitDeployment
    {
        return GitDeployment::where('user_id', auth()->id())
            ->whereKey($id)
            ->first();
    }

    public function selectDeployment(int $id): void
    {
        $deployment = $this->ownedDeployment($id);

        if (! $deployment) {
            session()->flash('error', 'No tenés acceso a ese repositorio.');

            return;
        }

        $this->selectedDeployment = $deployment;
        $this->domain_name = $deployment->domain_name;
        $this->deploy_path = $deployment->deploy_path ?? '';
        $this->repository_url = $deployment->repository_url;
        $this->branch = $deployment->branch;
        $this->deploy_script = $deployment->deploy_script;
        $this->auto_deploy = $deployment->auto_deploy;

        $this->isCreating = false;
        $this->activeTab = 'config';
        $this->selectedLog = null;
        $this->showLogModal = false;
        $this->webhookTestResult = null;
        $this->isDeploying = false;
        $this->deployStartedAt = null;
        $this->resetLogFilters();

        $this->refreshRepoStatus(app(GitService::class));
    }

    public function refreshRepoStatus(GitService $gitService): void
    {
        if (! $this->selectedDeployment) {
            return;
        }

        $this->selectedDeployment->refresh();
        $this->repoStatus = $gitService->getRepoStatus($this->selectedDeployment);
    }

    public function createNew(): void
    {
        $this->reset(['domain_name', 'deploy_path', 'repository_url', 'branch', 'auto_deploy']);
        $this->deploy_script = "composer install --no-interaction --prefer-dist --optimize-autoloader\nphp artisan migrate --force\nphp artisan config:cache\nnpm run build";
        $this->isCreating = true;
        $this->selectedDeployment = null;
        $this->selectedLog = null;
        $this->showLogModal = false;
        $this->repoStatus = [];
        $this->webhookTestResult = null;
        $this->resetPage();
    }

    public function cancelCreate(): void
    {
        $this->isCreating = false;

        if ($this->deployments->isNotEmpty()) {
            $this->selectDeployment($this->deployments->first()->id);
        }
    }

    public function save(): void
    {
        if (! $this->isCreating && ! $this->ownedDeployment($this->selectedDeployment?->id ?? 0)) {
            session()->flash('error', 'No tenés acceso a ese repositorio.');

            return;
        }

        $data = $this->validate();
        $data['repository_url'] = trim($data['repository_url']);
        $data['deploy_path'] = trim((string) ($data['deploy_path'] ?? ''));
        $data['user_id'] = auth()->id();

        if ($this->isCreating) {
            $deployment = GitDeployment::create($data);

            AuditLog::record('git.config.created', $deployment->domain_name, [
                'deployment_id' => $deployment->id,
                'repository_url' => $deployment->repository_url,
                'branch' => $deployment->branch,
            ]);

            $this->loadDeployments();
            $this->selectDeployment($deployment->id);
            session()->flash('message', 'Despliegue Git configurado correctamente.');

            return;
        }

        $this->selectedDeployment->update($data);

        AuditLog::record('git.config.updated', $this->selectedDeployment->domain_name, [
            'deployment_id' => $this->selectedDeployment->id,
            'branch' => $this->selectedDeployment->branch,
            'auto_deploy' => $this->selectedDeployment->auto_deploy,
        ]);

        $this->loadDeployments();
        $this->selectDeployment($this->selectedDeployment->id);
        session()->flash('message', 'Configuración actualizada.');
    }

    public function generateNewSecret(): void
    {
        if (! $this->selectedDeployment || ! $this->ownedDeployment($this->selectedDeployment->id)) {
            session()->flash('error', 'No tenés acceso a ese repositorio.');

            return;
        }

        $this->selectedDeployment->update(['webhook_secret' => Str::random(32)]);
        $this->loadDeployments();

        AuditLog::record('git.secret.regenerated', $this->selectedDeployment->domain_name, [
            'deployment_id' => $this->selectedDeployment->id,
        ]);

        session()->flash('message', 'Nuevo secreto generado.');
    }

    public function deployNow(?string $commitHash = null): void
    {
        if (! $this->selectedDeployment || ! $this->ownedDeployment($this->selectedDeployment->id)) {
            session()->flash('error', 'No tenés acceso a ese repositorio.');

            return;
        }

        if ($commitHash !== null && ! preg_match('/^[0-9a-f]{7,64}$/i', trim($commitHash))) {
            session()->flash('error', 'El hash del commit no es válido.');

            return;
        }

        $dispatcher = app(GitDeployDispatcher::class);
        $deploymentId = $this->selectedDeployment->id;

        if ($dispatcher->isRunning($deploymentId)) {
            session()->flash('error', 'Ya hay un despliegue en curso para este repositorio.');

            return;
        }

        try {
            $dispatcher->dispatch($deploymentId, 'manual', $commitHash ? strtolower(trim($commitHash)) : null);
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo iniciar el despliegue: ' . $e->getMessage());

            return;
        }

        $this->isDeploying = true;
        $this->deployStartedAt = now()->timestamp;
        $this->selectedLog = null;
        $this->showLogModal = false;

        AuditLog::record('git.deploy.started', $this->selectedDeployment->domain_name, [
            'deployment_id' => $deploymentId,
            'triggered_by' => 'manual',
            'commit' => $commitHash,
        ]);

        session()->flash('message', 'Despliegue iniciado en segundo plano. El historial se actualizará automáticamente.');
    }

    public function redeployCommit(int $logId): void
    {
        if (! $this->selectedDeployment || ! $this->ownedDeployment($this->selectedDeployment->id)) {
            session()->flash('error', 'No tenés acceso a ese repositorio.');

            return;
        }

        $log = $this->selectedDeployment->logs()->whereKey($logId)->first();

        if (! $log || ! $log->commit_hash) {
            session()->flash('error', 'No se encontró el commit para redesplegar.');

            return;
        }

        $this->showLogModal = false;
        $this->activeTab = 'config';
        $this->deployNow($log->commit_hash);
    }

    public function pollDeploymentStatus(): void
    {
        if (! $this->selectedDeployment || ! $this->isDeploying || ! $this->deployStartedAt) {
            return;
        }

        $since = Carbon::createFromTimestamp($this->deployStartedAt)->subSeconds(10);

        $log = $this->selectedDeployment->logs()
            ->where('triggered_by', 'manual')
            ->where('created_at', '>=', $since)
            ->first();

        if (! $log) {
            if (now()->timestamp - $this->deployStartedAt > 600) {
                $this->isDeploying = false;
                $this->deployStartedAt = null;
                session()->flash('error', 'El despliegue no registró actividad. Revisá storage/logs/git-deploy.log.');
            }

            return;
        }

        if (! in_array($log->status, ['success', 'failed'], true)) {
            return;
        }

        $this->isDeploying = false;
        $this->deployStartedAt = null;
        $this->selectedDeployment->refresh();
        $this->loadDeployments();
        $this->refreshRepoStatus(app(GitService::class));

        if ($log->status === 'success') {
            session()->flash('message', 'Actualización completada con éxito.');
        } else {
            session()->flash('error', 'El despliegue falló. Revisá el log para más detalles.');
        }
    }

    public function testWebhook(): void
    {
        if (! $this->selectedDeployment || ! $this->ownedDeployment($this->selectedDeployment->id)) {
            session()->flash('error', 'No tenés acceso a ese repositorio.');

            return;
        }

        $dep = $this->selectedDeployment;

        try {
            $payload = json_encode([
                'zen'  => 'LaraPanel webhook test',
                'test' => true,
                'ref'  => 'refs/heads/' . trim($dep->branch ?: 'main'),
            ]);

            $headers = [
                'X-Larapanel-Test'    => '1',
                'X-GitHub-Event'      => 'push',
                'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $payload, $dep->webhook_secret),
                'Accept'              => 'application/json',
            ];

            $response = Http::timeout(20)
                ->withHeaders($headers)
                ->withBody($payload, 'application/json')
                ->post($dep->webhook_url);

            if (! $response->successful()) {
                $this->webhookTestResult = [
                    'ok' => false,
                    'message' => 'El servidor respondió HTTP ' . $response->status() . '. Revisá que la URL sea accesible públicamente.',
                ];

                return;
            }

            $checks = $response->json('checks', []);
            $secret = $checks['secret'] ?? 'unknown';
            $auto = $checks['auto_deploy'] ?? 'unknown';

            if ($secret === 'invalid' || $secret === 'not_configured') {
                $this->webhookTestResult = [
                    'ok' => false,
                    'message' => 'El webhook responde pero el secreto no coincide o no está configurado. Regeneralo y actualizalo en GitHub/GitLab.',
                ];
            } elseif ($auto !== 'enabled') {
                $this->webhookTestResult = [
                    'ok' => false,
                    'message' => 'El webhook responde pero el Auto-Deploy está apagado. Activalo en Configuración.',
                ];
            } else {
                $this->webhookTestResult = [
                    'ok' => true,
                    'message' => 'Webhook OK: URL accesible, secreto válido y auto-deploy activo para la rama ' . ($checks['tracked_branch'] ?? '?') . '.',
                ];
            }
        } catch (\Throwable $e) {
            $this->webhookTestResult = [
                'ok' => false,
                'message' => 'No se pudo conectar con el webhook: ' . $e->getMessage(),
            ];
        }
    }

    public function viewLog(int $logId): void
    {
        if (! $this->selectedDeployment || ! $this->ownedDeployment($this->selectedDeployment->id)) {
            return;
        }

        $log = $this->selectedDeployment->logs()->whereKey($logId)->first();

        if (! $log) {
            return;
        }

        $this->selectedLog = $log;
        $this->showLogModal = true;
    }

    public function closeLogModal(): void
    {
        $this->showLogModal = false;
        $this->selectedLog = null;
    }

    public function delete(): void
    {
        if (! $this->selectedDeployment || ! $this->ownedDeployment($this->selectedDeployment->id)) {
            session()->flash('error', 'No tenés acceso a ese repositorio.');

            return;
        }

        $domain = $this->selectedDeployment->domain_name;
        $id = $this->selectedDeployment->id;

        $this->selectedDeployment->delete();
        $this->selectedDeployment = null;
        $this->closeLogModal();
        $this->loadDeployments();

        AuditLog::record('git.config.deleted', $domain, ['deployment_id' => $id]);

        session()->flash('message', 'Configuración de despliegue eliminada.');
    }

    protected function isValidRepositoryUrl(string $value): bool
    {
        $value = trim($value);

        return (bool) preg_match('#^https://[^\s]+$#i', $value)
            || (bool) preg_match('#^git@[A-Za-z0-9.\-]+:[^\s]+$#', $value)
            || (bool) preg_match('#^ssh://[^\s]+$#i', $value);
    }

    public function render()
    {
        $availableDomains = Domain::where('user_id', auth()->id())
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        $logs = null;

        if ($this->selectedDeployment) {
            $search = '%' . $this->logSearch . '%';

            $logs = $this->selectedDeployment->logs()
                ->when($this->logSearch !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('commit_hash', 'like', $search)
                        ->orWhere('commit_message', 'like', $search)
                        ->orWhere('triggered_by', 'like', $search);
                }))
                ->when($this->logStatus !== 'all', fn ($query) => $query->where('status', $this->logStatus))
                ->when($this->logTrigger !== 'all', fn ($query) => $query->where('triggered_by', $this->logTrigger))
                ->paginate($this->perPage);
        }

        return view('livewire.git.git-index', [
            'availableDomains' => $availableDomains,
            'logs' => $logs,
        ])->layout('layouts.app', [
            'title'      => 'Git Deploy',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Git Deploy</strong>',
        ]);
    }
}
