<?php

namespace App\Livewire\Git;

use App\Models\DeployPipeline;
use App\Models\DeployPipelineRun;
use App\Models\GitDeployment;
use App\Services\PipelineService;
use Livewire\Component;

class PipelineIndex extends Component
{
    public $pipelines;

    public ?DeployPipeline $selectedPipeline = null;

    public ?DeployPipelineRun $expandedRun = null;

    public ?string $expandedStage = null;

    public bool $isCreating = false;

    public string $name = '';

    public ?int $git_deployment_id = null;

    public string $branch = 'main';

    public bool $enable_webhook = true;

    /** @var array<int, array<string, mixed>> Editor de etapas. */
    public array $stages = [];

    protected array $rules = [
        'name'              => ['required', 'string', 'min:2', 'max:120'],
        'git_deployment_id' => ['nullable', 'integer'],
        'branch'            => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\-\.\/_]+$/'],
        'enable_webhook'    => 'boolean',
    ];

    public function mount()
    {
        abort_unless(auth()->user(), 403);

        $this->loadPipelines();
    }

    public function loadPipelines()
    {
        $this->pipelines = DeployPipeline::with('gitDeployment')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        if (! $this->selectedPipeline && $this->pipelines->isNotEmpty()) {
            $this->selectPipeline($this->pipelines->first()->id);
        }
    }

    public function selectPipeline(int $id)
    {
        $this->selectedPipeline = DeployPipeline::with('gitDeployment', 'runs')->find($id);

        if ($this->selectedPipeline && $this->selectedPipeline->user_id !== auth()->id() && ! auth()->user()?->isAdmin()) {
            $this->selectedPipeline = null;

            return;
        }

        $this->isCreating = false;
        $this->expandedRun = null;
        $this->expandedStage = null;

        if ($this->selectedPipeline) {
            $this->name = $this->selectedPipeline->name;
            $this->git_deployment_id = $this->selectedPipeline->git_deployment_id;
            $this->branch = $this->selectedPipeline->branch;
            $this->enable_webhook = (bool) $this->selectedPipeline->enable_webhook;
            $this->stages = array_map(fn (array $stage) => [
                'id'              => (string) ($stage['id'] ?? ''),
                'name'            => (string) ($stage['name'] ?? ''),
                'command'         => (string) ($stage['command'] ?? ''),
                'script'          => (string) ($stage['script'] ?? ''),
                'branch_override' => (string) ($stage['branch_override'] ?? ''),
                'env'             => $this->envToText((array) ($stage['env'] ?? [])),
            ], $this->selectedPipeline->stages ?? []);
        }
    }

    public function createNew()
    {
        $this->reset(['name', 'git_deployment_id', 'branch', 'enable_webhook', 'expandedRun']);
        $this->branch = 'main';
        $this->enable_webhook = true;
        $this->isCreating = true;
        $this->selectedPipeline = null;
        $this->stages = [[
            'id'              => '',
            'name'            => 'Instalar dependencias',
            'command'         => 'composer install --no-interaction --prefer-dist --optimize-autoloader',
            'script'          => '',
            'branch_override' => '',
            'env'             => '',
        ]];
    }

    public function addStage()
    {
        $this->stages[] = [
            'id'              => '',
            'name'            => '',
            'command'         => '',
            'script'          => '',
            'branch_override' => '',
            'env'             => '',
        ];
    }

    public function removeStage(int $index)
    {
        unset($this->stages[$index]);
        $this->stages = array_values($this->stages);
    }

    public function moveStage(int $index, int $direction)
    {
        $target = $index + $direction;
        if ($target < 0 || $target >= count($this->stages)) {
            return;
        }

        $stages = $this->stages;
        [$stages[$index], $stages[$target]] = [$stages[$target], $stages[$index]];
        $this->stages = array_values($stages);
    }

    public function save(PipelineService $service)
    {
        try {
            $data = $this->validate();
            $data['stages'] = $this->parseStages();

            if ($data['stages'] === []) {
                throw new \InvalidArgumentException('Agrega al menos una etapa con nombre.');
            }

            if ($this->isCreating) {
                $pipeline = $service->createForUser($data);
                session()->flash('message', 'Pipeline creado correctamente.');
            } else {
                $service->updateForUser($this->selectedPipeline, $data);
                $pipeline = $this->selectedPipeline;
                session()->flash('message', 'Pipeline actualizado.');
            }

            $this->loadPipelines();
            $this->selectPipeline($pipeline->id);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function runPipeline(PipelineService $service)
    {
        if (! $this->selectedPipeline) {
            return;
        }

        if ($this->selectedPipeline->last_run_status === 'running' || $this->selectedPipeline->last_run_status === 'queued') {
            session()->flash('error', 'Ya hay una ejecución en curso para este pipeline.');

            return;
        }

        try {
            $pipeline = DeployPipeline::find($this->selectedPipeline->id);

            $run = $service->runStreaming($pipeline, function (string $chunk) {
                $this->stream(
                    to: 'pipeline-run-log',
                    content: nl2br(e($chunk)),
                    replace: false,
                );
            }, 'manual');

            $this->loadPipelines();
            $this->expandedRun = DeployPipelineRun::with('pipeline')->find($run->id);

            if ($run->status === 'success') {
                session()->flash('message', 'Pipeline ejecutado con éxito en ' . $this->formatMs($run->totalDurationMs()) . '.');
            } else {
                session()->flash('error', 'El pipeline falló. Revisá el detalle por etapas.');
            }
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo ejecutar el pipeline: ' . $e->getMessage());
            $this->loadPipelines();
        }
    }

    public function viewRun(int $runId)
    {
        $run = DeployPipelineRun::with('pipeline')->find($runId);

        if (! $run || $run->pipeline->user_id !== auth()->id()) {
            return;
        }

        $this->expandedRun = $run;
    }

    public function deletePipeline(PipelineService $service)
    {
        if (! $this->selectedPipeline) {
            return;
        }

        try {
            $service->delete($this->selectedPipeline);
            $this->selectedPipeline = null;
            $this->loadPipelines();
            session()->flash('message', 'Pipeline eliminado.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $availableDeployments = GitDeployment::where('user_id', auth()->id())->orderBy('domain_name')->get();

        return view('livewire.git.pipeline-index', [
            'availableDeployments' => $availableDeployments,
        ])->layout('layouts.app', [
            'title'      => 'Pipeline de Despliegue',
            'breadcrumb' => '<span>Git Deploy</span> / <strong>Pipelines</strong>',
        ]);
    }

    protected function parseStages(): array
    {
        $out = [];

        foreach ($this->stages as $i => $stage) {
            $name = trim((string) ($stage['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $env = [];
            foreach (preg_split('/\r\n|\r|\n/', (string) ($stage['env'] ?? '')) as $kv) {
                $kv = trim($kv);
                if ($kv === '' || ! str_contains($kv, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $kv, 2);
                $k = trim($k);
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $k)) {
                    $env[$k] = trim($v);
                }
            }

            $out[] = [
                'id'              => (string) ($stage['id'] ?? '') !== '' ? $stage['id'] : 'stage_' . ($i + 1),
                'name'            => $name,
                'command'         => (string) ($stage['command'] ?? ''),
                'script'          => (string) ($stage['script'] ?? ''),
                'branch_override' => (string) ($stage['branch_override'] ?? ''),
                'env'             => $env,
            ];
        }

        return $out;
    }

    protected function envToText(array $env): string
    {
        $lines = [];
        foreach ($env as $k => $v) {
            $lines[] = $k . '=' . $v;
        }

        return implode("\n", $lines);
    }

    protected function formatMs(int $ms): string
    {
        if ($ms < 1000) {
            return $ms . ' ms';
        }

        return number_format($ms / 1000, 1, ',', '.') . ' s';
    }
}