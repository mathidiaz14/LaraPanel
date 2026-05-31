<?php

namespace App\Livewire\Servers;

use App\Models\Server;
use App\Services\ServerService;
use App\Shell\RemoteShellExecutor;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class ServersIndex extends Component
{
    // UI states
    public string $activeTab = 'info'; // info | terminal | ssh-key | add-server
    public ?int $selectedServerId = null;

    // Add Server Form
    public string $serverName = '';
    public string $hostname = '';
    public int $port = 22;
    public string $username = 'root';
    public string $authType = 'key'; // key | password
    public string $sshPrivateKey = '';
    public string $sshPassword = '';
    public string $notes = '';

    // Terminal
    public string $terminalCommand = '';
    public string $terminalOutput = '';
    public bool $isExecuting = false;

    // SSH Keys generation
    public string $generatedPrivateKey = '';
    public string $generatedPublicKey = '';

    public string $successMessage = '';
    public string $errorMessage = '';

    protected array $rules = [
        'serverName' => 'required|string|min:2|max:255',
        'hostname'   => 'required|string|min:2|max:255',
        'port'       => 'required|integer|min:1|max:65535',
        'username'   => 'required|string|min:1|max:255',
        'authType'   => 'required|in:key,password',
    ];

    public function mount(ServerService $service): void
    {
        // Enforce a local server entry if one doesn't exist
        $local = Server::where('user_id', auth()->id())->where('is_local', true)->first();
        if (!$local) {
            $local = Server::create([
                'user_id' => auth()->id(),
                'name' => 'Este Servidor',
                'hostname' => '127.0.0.1',
                'port' => 22,
                'username' => 'root',
                'is_local' => true,
                'status' => 'online',
            ]);
        }

        $this->selectedServerId = $local->id;
        $this->pingServer($local->id, $service);
    }

    public function selectServer(int $id, ServerService $service): void
    {
        $this->selectedServerId = $id;
        $this->activeTab = 'info';
        $this->terminalOutput = '';
        $this->terminalCommand = '';
        $this->successMessage = '';
        $this->errorMessage = '';

        $this->pingServer($id, $service);
    }

    public function pingServer(int $id, ServerService $service): void
    {
        $server = Server::where('user_id', auth()->id())->findOrFail($id);
        $service->ping($server);
    }

    public function generateKeys(ServerService $service): void
    {
        try {
            $keys = $service->generateKeyPair();
            $this->generatedPrivateKey = $keys['private'];
            $this->generatedPublicKey = $keys['public'];
            $this->sshPrivateKey = $keys['private'];
            $this->successMessage = "¡Par de claves SSH de 4096 bits generado con éxito! Guarda la clave pública.";
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al generar claves: " . $e->getMessage();
        }
    }

    public function saveServer(ServerService $service): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage = '';

        if ($this->authType === 'key' && empty($this->sshPrivateKey)) {
            $this->errorMessage = "Debes ingresar o generar una clave privada SSH.";
            return;
        }

        if ($this->authType === 'password' && empty($this->sshPassword)) {
            $this->errorMessage = "Debes ingresar la contraseña SSH.";
            return;
        }

        try {
            $server = Server::create([
                'user_id' => auth()->id(),
                'name' => $this->serverName,
                'hostname' => $this->hostname,
                'port' => $this->port,
                'username' => $this->username,
                'auth_type' => $this->authType,
                'ssh_private_key' => $this->authType === 'key' ? $this->sshPrivateKey : null,
                'ssh_password' => $this->authType === 'password' ? $this->sshPassword : null,
                'notes' => $this->notes,
                'status' => 'unknown',
            ]);

            // Auto-ping the new server
            $service->ping($server);

            $this->successMessage = "Servidor remoto «{$server->name}» agregado con éxito.";
            $this->resetForm();
            $this->selectedServerId = $server->id;
            $this->activeTab = 'info';
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al guardar servidor: " . $e->getMessage();
        }
    }

    public function deleteServer(int $id): void
    {
        $server = Server::where('user_id', auth()->id())->findOrFail($id);
        if ($server->is_local) {
            $this->errorMessage = "No se puede eliminar el servidor local de LaraPanel.";
            return;
        }

        // Switch context to local if active server is deleted
        if (session('active_server_id') == $id) {
            session()->forget('active_server_id');
        }

        $server->delete();
        $this->selectedServerId = Server::where('user_id', auth()->id())->where('is_local', true)->value('id');
        $this->activeTab = 'info';
        $this->successMessage = "Servidor eliminado con éxito.";
    }

    public function runTerminalCommand(): void
    {
        if (empty(trim($this->terminalCommand))) {
            return;
        }

        $this->isExecuting = true;
        $this->terminalOutput = '';
        $this->errorMessage = '';

        try {
            $server = Server::where('user_id', auth()->id())->findOrFail($this->selectedServerId);

            if ($server->is_local) {
                // Command executor locally
                $executor = new \App\Shell\ShellExecutor();
                // Basic parsing to array for run
                $cmdArray = array_values(array_filter(explode(' ', $this->terminalCommand)));
                $res = $executor->run($cmdArray, checkExit: false);
                $this->terminalOutput = $res->stdout . "\n" . $res->stderr;
            } else {
                // Remote execution via RemoteShellExecutor
                $executor = new RemoteShellExecutor($server);
                $cmdArray = array_values(array_filter(explode(' ', $this->terminalCommand)));
                $res = $executor->run($cmdArray, checkExit: false);
                $this->terminalOutput = $res->stdout . "\n" . $res->stderr;
            }
        } catch (\Throwable $e) {
            $this->terminalOutput = "❌ Error: " . $e->getMessage();
        } finally {
            $this->isExecuting = false;
        }
    }

    protected function resetForm(): void
    {
        $this->reset([
            'serverName', 'hostname', 'port', 'username', 'authType',
            'sshPrivateKey', 'sshPassword', 'notes',
            'generatedPrivateKey', 'generatedPublicKey'
        ]);
        $this->port = 22;
        $this->username = 'root';
        $this->authType = 'key';
    }

    public function render()
    {
        $servers = Server::where('user_id', auth()->id())->orderBy('is_local', 'desc')->orderBy('name')->get();
        $selectedServer = Server::where('user_id', auth()->id())->find($this->selectedServerId);

        return view('livewire.servers.servers-index', [
            'servers' => $servers,
            'selectedServer' => $selectedServer,
        ])->layout('layouts.app', [
            'title' => 'Gestor de Servidores',
            'breadcrumb' => '<span>Infraestructura</span> / <strong>Servidores (Cluster)</strong>',
        ]);
    }
}
