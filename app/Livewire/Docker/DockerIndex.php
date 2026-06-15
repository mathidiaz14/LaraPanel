<?php

namespace App\Livewire\Docker;

use App\Models\DockerContainer;
use App\Services\DockerService;
use Livewire\Component;

class DockerIndex extends Component
{
    // ── UI State ──────────────────────────────────────────────────────────────
    public string $activeTab = 'containers'; // containers | images | compose | deploy
    public bool $daemonRunning = false;

    // ── Containers tab ────────────────────────────────────────────────────────
    public array  $containers        = [];
    public array  $groupedContainers = []; // Containers grouped by prefix
    public ?array $selectedContainer = null;
    public string $containerLogs    = '';
    public int    $logLines         = 100;
    public bool   $showLogs         = false;
    public string $actionOutput     = '';
    
    // ── Terminal state ────────────────────────────────────────────────────────
    public bool   $showTerminal     = false;
    public string $terminalContainer= '';
    public string $terminalCommand  = '';
    public string $terminalOutput   = '';

    // ── Images tab ────────────────────────────────────────────────────────────
    public array  $images    = [];
    public string $pullImage = '';
    public string $pullOutput = '';
    public bool   $isPulling = false;

    // ── Compose tab ───────────────────────────────────────────────────────────
    public string $composeName    = '';
    public string $composeContent = '';
    public string $composeOutput  = '';

    // ── Deploy tab ────────────────────────────────────────────────────────────
    public string $deployPath     = '';
    public string $deployDomain   = '';
    public string $deployPort     = '';
    public string $deployOutput   = '';

    // Default compose template shown to new users
    private const COMPOSE_TEMPLATE = "version: '3.8'\nservices:\n  app:\n    image: nginx:alpine\n    ports:\n      - \"8081:80\"\n    volumes:\n      - ./html:/usr/share/nginx/html\n    restart: unless-stopped\n";

    protected array $rules = [
        'composeName'    => 'required|regex:/^[a-zA-Z0-9_\-]+$/|max:64',
        'composeContent' => 'required|string|min:10',
        'pullImage'      => 'required|regex:/^[a-zA-Z0-9\/_\-.:]+$/|max:200',
        'deployPath'     => 'required|string|max:255',
        'deployDomain'   => 'required|regex:/^[a-zA-Z0-9\.\-]+$/|max:255',
        'deployPort'     => 'required|integer|min:1|max:65535',
    ];

    protected array $messages = [
        'composeName.regex'   => 'El nombre del stack solo puede contener letras, números, guiones y guiones bajos.',
        'pullImage.regex'     => 'Nombre de imagen no válido.',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    public function mount(DockerService $docker): void
    {
        $this->daemonRunning = $docker->isDaemonRunning();
        $this->composeContent = self::COMPOSE_TEMPLATE;

        if ($this->daemonRunning) {
            $this->loadContainers($docker);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   CONTAINERS
    // ─────────────────────────────────────────────────────────────────────────

    public function loadContainers(DockerService $docker): void
    {
        $this->containers = $docker->listContainers();
        $this->groupedContainers = $this->groupByPrefix($this->containers);
    }

    /**
     * Group containers by their name prefix (everything before the first dash).
     * e.g. orbit-app, orbit-web → ['orbit' => [...]]
     * Containers with no dash go into 'otros'.
     */
    protected function groupByPrefix(array $containers): array
    {
        $groups = [];
        foreach ($containers as $c) {
            $prefix = str_contains($c['name'], '-')
                ? explode('-', $c['name'])[0]
                : 'otros';
            $groups[$prefix][] = $c;
        }
        ksort($groups);
        return $groups;
    }

    public function selectContainer(string $name, DockerService $docker): void
    {
        $this->selectedContainer = collect($this->containers)->firstWhere('name', $name)
            ?? collect($this->containers)->first(fn($c) => str_contains($c['name'], $name));
        $this->showLogs = false;
        $this->containerLogs = '';
        $this->actionOutput = '';
    }

    public function startContainer(string $name, DockerService $docker): void
    {
        try {
            $docker->start($name);
            $this->loadContainers($docker);
            $this->actionOutput = "✅ Contenedor '{$name}' iniciado correctamente.";
        } catch (\Throwable $e) {
            $this->actionOutput = "❌ Error: " . $e->getMessage();
        }
    }

    public function stopContainer(string $name, DockerService $docker): void
    {
        try {
            $docker->stop($name);
            $this->loadContainers($docker);
            $this->actionOutput = "⏹ Contenedor '{$name}' detenido.";
        } catch (\Throwable $e) {
            $this->actionOutput = "❌ Error: " . $e->getMessage();
        }
    }

    public function restartContainer(string $name, DockerService $docker): void
    {
        try {
            $docker->restart($name);
            $this->loadContainers($docker);
            $this->actionOutput = "🔄 Contenedor '{$name}' reiniciado.";
        } catch (\Throwable $e) {
            $this->actionOutput = "❌ Error: " . $e->getMessage();
        }
    }

    public function removeContainer(string $name, DockerService $docker): void
    {
        try {
            $docker->remove($name);
            // Also remove from DB if registered
            DockerContainer::where('name', $name)->where('user_id', auth()->id())->delete();
            $this->selectedContainer = null;
            $this->loadContainers($docker);
            $this->actionOutput = "🗑 Contenedor '{$name}' eliminado.";
        } catch (\Throwable $e) {
            $this->actionOutput = "❌ Error: " . $e->getMessage();
        }
    }

    public function viewLogs(string $name, DockerService $docker): void
    {
        $this->containerLogs = $docker->logs($name, $this->logLines);
        $this->showLogs = true;
    }

    public function refreshLogs(DockerService $docker): void
    {
        if ($this->selectedContainer) {
            $this->containerLogs = $docker->logs($this->selectedContainer['name'], $this->logLines);
        }
    }

    public function openTerminal(string $name): void
    {
        $this->terminalContainer = $name;
        $this->terminalCommand = '';
        $this->terminalOutput = "Welcome to LaraPanel Docker Console.\nConnected to: {$name}\nType a command and press Enter.\n\n";
        $this->showTerminal = true;
    }

    public function runTerminalCommand(DockerService $docker): void
    {
        $cmd = trim($this->terminalCommand);
        if (empty($cmd)) return;

        if ($cmd === 'clear') {
            $this->terminalOutput = '';
            $this->terminalCommand = '';
            return;
        }

        $this->terminalOutput .= "\n$ " . $cmd . "\n";
        $result = $docker->execContainerCommand($this->terminalContainer, $cmd);
        $this->terminalOutput .= $result;
        $this->terminalCommand = '';
    }

    public function closeTerminal(): void
    {
        $this->showTerminal = false;
        $this->terminalContainer = '';
        $this->terminalCommand = '';
        $this->terminalOutput = '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   IMAGES
    // ─────────────────────────────────────────────────────────────────────────

    public function loadImages(DockerService $docker): void
    {
        $this->images = $docker->listImages();
        $this->activeTab = 'images';
    }

    public function pullImageAction(DockerService $docker): void
    {
        $this->validateOnly('pullImage');
        $this->isPulling = true;
        $this->pullOutput = "Descargando imagen '{$this->pullImage}'...\n";

        try {
            $this->pullOutput = $docker->pullImage($this->pullImage);
            $this->loadImages($docker);
            $this->pullImage = '';
        } catch (\Throwable $e) {
            $this->pullOutput = "❌ Error: " . $e->getMessage();
        } finally {
            $this->isPulling = false;
        }
    }

    public function removeImage(string $image, DockerService $docker): void
    {
        try {
            $docker->removeImage($image);
            $this->loadImages($docker);
            $this->pullOutput = "🗑 Imagen '{$image}' eliminada.";
        } catch (\Throwable $e) {
            $this->pullOutput = "❌ Error: " . $e->getMessage();
        }
    }

    public function pruneImages(DockerService $docker): void
    {
        $this->pullOutput = $docker->pruneImages();
        $this->loadImages($docker);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   COMPOSE
    // ─────────────────────────────────────────────────────────────────────────

    public function switchToCompose(DockerService $docker): void
    {
        $this->activeTab = 'compose';
    }

    public function loadComposeStack(string $stackName, DockerService $docker): void
    {
        $this->composeName    = $stackName;
        $this->composeContent = $docker->getComposeContent($stackName) ?: self::COMPOSE_TEMPLATE;
        $this->composeOutput  = '';
        $this->activeTab = 'compose';
    }

    public function getComposeContent(DockerService $docker): void
    {
        $this->validate(['composeName' => 'required|regex:/^[a-zA-Z0-9_\-]+$/|max:64']);
        $this->composeContent = $docker->getComposeContent($this->composeName);
        if (empty($this->composeContent)) {
            $this->composeContent = self::COMPOSE_TEMPLATE;
            $this->composeOutput = "No se encontró configuración previa para '{$this->composeName}'.";
        } else {
            $this->composeOutput = "Configuración cargada correctamente.";
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   DEPLOY (PROXY + COMPOSE)
    // ─────────────────────────────────────────────────────────────────────────

    public function deployApp(DockerService $docker, \App\Services\DomainService $domainService): void
    {
        $this->validate([
            'deployPath'   => 'required|string|max:255',
            'deployDomain' => 'required|regex:/^[a-zA-Z0-9\.\-]+$/|max:255',
            'deployPort'   => 'required|integer|min:1|max:65535',
        ]);

        $this->deployOutput = '';

        try {
            // 1. Validate if path exists
            if (!is_dir($this->deployPath)) {
                throw new \Exception("La ruta especificada no existe en el servidor.");
            }

            // 2. Register/Update Domain in DB as proxy
            $domain = \App\Models\Domain::firstOrNew(['name' => $this->deployDomain]);
            $domain->user_id = \Illuminate\Support\Facades\Auth::id() ?? 1;
            $domain->type = 'proxy';
            $domain->document_root = $this->deployPath;
            $domain->php_version = '8.3'; // Arbitrary, not used by proxy but required by schema
            $domain->webserver = 'nginx';
            $domain->config = array_merge($domain->config ?? [], ['proxy_port' => $this->deployPort]);
            $domain->is_active = true;
            $domain->status = 'active';
            $domain->save();

            // 3. Generate and deploy Nginx config
            $domainService->deployConfigs($domain);

            // 4. Run Docker Compose in the path
            $result = $docker->runComposeInPath($this->deployPath, 'up', ['-d', '--remove-orphans']);
            
            $this->deployOutput = "✅ App desplegada correctamente.\n\n" . 
                                  "Dominio: http://{$this->deployDomain} -> Puerto {$this->deployPort}\n\n" .
                                  "Logs de Docker:\n" . $result;

            $this->loadContainers($docker);
            
        } catch (\Throwable $e) {
            $this->deployOutput = "❌ Error en el despliegue:\n" . $e->getMessage();
        }
    }

    public function deployCompose(DockerService $docker): void
    {
        $this->validate([
            'composeName'    => $this->rules['composeName'],
            'composeContent' => $this->rules['composeContent'],
        ]);

        $this->composeOutput = "🚀 Desplegando stack '{$this->composeName}'...\n";

        try {
            $this->composeOutput = $docker->composeUp($this->composeName, $this->composeContent);
        } catch (\Throwable $e) {
            $this->composeOutput = "❌ Error: " . $e->getMessage();
        }

        $this->loadContainers($docker);
    }

    public function stopCompose(DockerService $docker): void
    {
        if (empty($this->composeName)) return;

        try {
            $this->composeOutput = $docker->composeDown($this->composeName);
        } catch (\Throwable $e) {
            $this->composeOutput = "❌ Error: " . $e->getMessage();
        }

        $this->loadContainers($docker);
    }

    public function viewComposeLogs(DockerService $docker): void
    {
        if (empty($this->composeName)) return;
        $this->composeOutput = $docker->composeLogs($this->composeName, $this->logLines);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function setTab(string $tab, DockerService $docker): void
    {
        $this->activeTab = $tab;

        if ($tab === 'containers') {
            $this->loadContainers($docker);
        } elseif ($tab === 'images') {
            $this->loadImages($docker);
        }
    }

    public function render(): \Illuminate\View\View
    {
        $availableDomains = \App\Models\Domain::where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        return view('livewire.docker.docker-index', [
            'availableDomains' => $availableDomains
        ])->layout('layouts.app', [
            'title'      => 'Docker',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Docker</strong>',
        ]);
    }
}
