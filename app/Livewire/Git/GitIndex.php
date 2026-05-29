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

    // Form fields
    public bool $isCreating = false;
    public string $domain_name = '';
    public string $repository_url = '';
    public string $branch = 'main';
    public string $deploy_script = "composer install --no-interaction --prefer-dist --optimize-autoloader\nphp artisan migrate --force\nphp artisan config:cache\nnpm run build";
    public bool $auto_deploy = true;

    protected array $rules = [
        'domain_name'    => 'required|string|max:255',
        'repository_url' => 'required|url|max:255',
        'branch'         => 'required|string|max:100',
        'deploy_script'  => 'nullable|string',
        'auto_deploy'    => 'boolean',
    ];

    public function mount()
    {
        $this->loadDeployments();
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
        $this->isCreating = false;
        $this->activeTab = 'config';
        $this->selectedLog = null;
    }

    public function createNew()
    {
        $this->reset(['domain_name', 'repository_url', 'branch', 'auto_deploy']);
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
            $this->selectedDeployment->load('logs');
            $this->viewLog($log->id);
            session()->flash('message', 'Despliegue manual completado.');
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
        return view('livewire.git.git-index')->layout('layouts.app', [
            'title'      => 'Git Deploy',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Git Deploy</strong>',
        ]);
    }
}
