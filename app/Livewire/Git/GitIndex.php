<?php

namespace App\Livewire\Git;

use App\Models\GitDeployment;
use App\Models\GitDeploymentLog;
use App\Services\GitService;
use Livewire\Component;

class GitIndex extends Component
{
    public $deployments;
    public ?GitDeployment $selectedDeployment = null;
    public ?GitDeploymentLog $selectedLog = null;
    public string $activeTab = 'config'; // config | logs
    public array $repoStatus = [];
    public ?array $webhookTestResult = null;

    // Form fields
    public bool $isCreating = false;
    public string $domain_name = '';
    public string $deploy_path = '';
    public string $repository_url = '';
    public string $branch = 'main';
    public string $deploy_script = "composer install --no-interaction --prefer-dist --optimize-autoloader\nphp artisan migrate --force\nphp artisan config:cache\nnpm run build";
    public bool $auto_deploy = true;

    protected array $rules = [
        'domain_name'    => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\-\.]+$/'],
        'deploy_path'    => ['nullable', 'string', 'max:255', 'regex:/^\/[a-zA-Z0-9\-\.\/_]+$/'],
        'repository_url' => ['required', 'string', 'max:255', 'regex:/^https?:\/\/[^\s]+$/'],
        'branch'         => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\-\.\/_]+$/'],
        'deploy_script'  => 'nullable|string',
        'auto_deploy'    => 'boolean',
    ];

    public function mount()
    {
        $this->loadDeployments();
    }

    public function updatedDomainName($value)
    {
        if ($value) {
            $domain = \App\Models\Domain::where('name', $value)->first();
            if ($domain) {
                $this->deploy_path = $domain->document_root;
            } else {
                $this->deploy_path = "/var/www/{$value}/public_html";
            }
        }
    }

    public function loadDeployments()
    {
        $this->deployments = GitDeployment::where('user_id', auth()->id())->get();
        if (!$this->selectedDeployment && $this->deployments->isNotEmpty()) {
            $this->selectDeployment($this->deployments->first()->id);
        }
    }

    public function selectDeployment(int $id)
    {
        $this->selectedDeployment = GitDeployment::with('logs')->find($id);
        
        // Map to form fields for editing
        $this->domain_name = $this->selectedDeployment->domain_name;
        $this->deploy_path = $this->selectedDeployment->deploy_path ?? '';
        $this->repository_url = $this->selectedDeployment->repository_url;
        $this->branch = $this->selectedDeployment->branch;
        $this->deploy_script = $this->selectedDeployment->deploy_script;
        $this->auto_deploy = $this->selectedDeployment->auto_deploy;

        $this->isCreating = false;
        $this->activeTab = 'config';
        $this->selectedLog = null;
        $this->webhookTestResult = null;

        $this->refreshRepoStatus(app(GitService::class));
    }

    public function refreshRepoStatus(GitService $gitService)
    {
        if ($this->selectedDeployment) {
            $this->repoStatus = $gitService->getRepoStatus($this->selectedDeployment);
        }
    }

    public function createNew()
    {
        $this->reset(['domain_name', 'deploy_path', 'repository_url', 'branch', 'auto_deploy']);
        $this->deploy_script = "composer install --no-interaction --prefer-dist --optimize-autoloader\nphp artisan migrate --force\nphp artisan config:cache\nnpm run build";
        $this->isCreating = true;
        $this->selectedDeployment = null;
    }

    public function save()
    {
        $data = $this->validate();
        $data['user_id'] = auth()->id();

        if ($this->isCreating) {
            $dep = GitDeployment::create($data);
            $this->loadDeployments();
            $this->selectDeployment($dep->id);
            session()->flash('message', 'Despliegue Git configurado correctamente.');
        } else {
            $this->selectedDeployment->update($data);
            $this->loadDeployments();
            session()->flash('message', 'Configuración actualizada.');
        }
    }

    public function generateNewSecret()
    {
        if ($this->selectedDeployment) {
            $this->selectedDeployment->update(['webhook_secret' => \Illuminate\Support\Str::random(32)]);
            $this->loadDeployments();
            session()->flash('message', 'Nuevo secreto generado.');
        }
    }

    public function deployNow(GitService $gitService)
    {
        if ($this->selectedDeployment) {
            $log = $gitService->deploy($this->selectedDeployment, 'manual');
            $this->selectedDeployment->refresh();
            $this->selectedDeployment->load('logs');
            $this->refreshRepoStatus($gitService);
            $this->viewLog($log->id);

            if ($log->status === 'success') {
                session()->flash('message', 'Actualización forzada completada con éxito.');
            } else {
                session()->flash('error', 'El despliegue falló. Revisá el log para más detalles.');
            }
        }
    }

    /**
     * Sends a signed test request to the public webhook URL to verify
     * connectivity, route and secret without triggering a real deploy.
     */
    public function testWebhook()
    {
        if (!$this->selectedDeployment) {
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
                'X-Larapanel-Test'   => '1',
                'X-GitHub-Event'     => 'push',
                'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $payload, $dep->webhook_secret),
                'Accept'             => 'application/json',
            ];

            $response = \Illuminate\Support\Facades\Http::timeout(20)
                ->withHeaders($headers)
                ->withBody($payload, 'application/json')
                ->post($dep->webhook_url);

            if ($response->successful()) {
                $data = $response->json();
                $checks = $data['checks'] ?? [];
                $secret = $checks['secret'] ?? 'unknown';
                $auto   = $checks['auto_deploy'] ?? 'unknown';

                if ($secret === 'invalid') {
                    $this->webhookTestResult = [
                        'ok' => false,
                        'message' => 'El webhook responde pero el secreto configurado NO coincide. Regeneralo y actualizalo en GitHub/GitLab.',
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
            } else {
                $this->webhookTestResult = [
                    'ok' => false,
                    'message' => 'El servidor respondió HTTP ' . $response->status() . '. Revisá que la URL sea accesible públicamente.',
                ];
            }
        } catch (\Throwable $e) {
            $this->webhookTestResult = [
                'ok' => false,
                'message' => 'No se pudo conectar con el webhook: ' . $e->getMessage(),
            ];
        }
    }

    public function viewLog(int $logId)
    {
        $this->selectedLog = GitDeploymentLog::find($logId);
        $this->activeTab = 'logs';
    }

    public function delete()
    {
        if ($this->selectedDeployment) {
            $this->selectedDeployment->delete();
            $this->selectedDeployment = null;
            $this->loadDeployments();
            session()->flash('message', 'Configuración de despliegue eliminada.');
        }
    }

    public function render()
    {
        $availableDomains = \App\Models\Domain::where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        return view('livewire.git.git-index', [
            'availableDomains' => $availableDomains
        ])->layout('layouts.app', [
            'title'      => 'Git Deploy',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Git Deploy</strong>',
        ]);
    }
}
