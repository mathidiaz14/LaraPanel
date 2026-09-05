<?php

namespace App\Livewire\RemoteFtp;

use App\Models\Domain;
use App\Models\RemoteFtpConnection;
use App\Services\RemoteFtpService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;

class RemoteFtpIndex extends Component
{
    // Connection form
    public string $name = '';
    public string $host = '';
    public int $port = 21;
    public string $protocol = 'ftp';
    public string $username = '';
    public string $password = '';
    public bool $passive = true;
    public string $initialPath = '/';

    // Active (session) connection
    public bool $connected = false;
    public string $remoteCwd = '/';
    public array $listing = [];
    public ?int $activeConnectionId = null;

    // Target
    public ?int $targetDomainId = null;
    public string $targetPath = '';

    // Copy modal
    public ?array $copyItem = null;
    public string $copyTarget = '';

    // Jobs
    public array $jobs = [];
    public ?string $selectedJob = null;
    public string $jobLog = '';

    // UI
    public string $errorMessage = '';
    public string $successMessage = '';

    protected array $rules = [
        'name'     => 'required|string|max:100',
        'host'     => 'required|string|max:255',
        'port'     => 'required|integer|min:1|max:65535',
        'protocol' => 'required|in:ftp,ftps',
        'username' => 'required|string|max:100',
        'password' => 'nullable|string|max:255',
    ];

    public function mount(): void
    {
        $this->targetDomainId = auth()->user()?->domains()->orderBy('name')->value('id');
    }

    // ─────────────────────────── render ───────────────────────────

    public function render()
    {
        return view('livewire.remote-ftp.remote-ftp-index', [
            'domains'    => Domain::where('user_id', auth()->id())->orderBy('name')->get(),
            'connections' => RemoteFtpConnection::where('user_id', auth()->id())->orderByDesc('id')->get(),
        ]);
    }

    // ─────────────────────────── connections ───────────────────────────

    public function saveConnection(RemoteFtpService $ftp): void
    {
        $this->validate();
        $this->clearMessages();

        try {
            $connection = RemoteFtpConnection::create([
                'user_id'      => auth()->id(),
                'name'         => $this->name,
                'host'         => $this->host,
                'port'         => $this->port,
                'protocol'     => $this->protocol,
                'username'     => $this->username,
                'password'     => $this->password ?: null,
                'passive'      => $this->passive,
                'initial_path' => $this->initialPath,
            ]);

            $this->successMessage = "Conexión remota '{$connection->name}' guardada.";

            // Auto-connect after saving if a password was provided
            if ($this->password !== '') {
                $this->password = '';
                $this->connectTo($connection->id, $ftp);
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function connectTo(?int $connectionId, RemoteFtpService $ftp): void
    {
        $this->clearMessages();

        try {
            $connection = null;
            $password = '';

            if ($connectionId !== null) {
                $connection = RemoteFtpConnection::where('user_id', auth()->id())->findOrFail($connectionId);
                $password = $connection->password ?? $this->password;
                if ($password === null || $password === '') {
                    $this->errorMessage = "La conexión '{$connection->name}' no tiene contraseña guardada. Escribila abajo.";
                    return;
                }
            } else {
                $this->validate();
                $connection = new RemoteFtpConnection([
                    'host'         => $this->host,
                    'port'         => $this->port,
                    'protocol'     => $this->protocol,
                    'username'     => $this->username,
                    'passive'      => $this->passive,
                    'initial_path' => $this->initialPath,
                ]);
                $password = $this->password;
            }

            $this->testAndActivate($ftp, $connection, $password, $connectionId);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->connected = false;
        }
    }

    private function testAndActivate(
        RemoteFtpService $ftp,
        RemoteFtpConnection $connection,
        string $password,
        ?int $connectionId
    ): void {
        $conn = $ftp->connect($connection, $password);
        $this->storeSession($connection, $password);
        $this->connected = true;
        $this->activeConnectionId = $connectionId;
        $this->remoteCwd = $ftp->pwd($conn);
        $this->listing = $ftp->list($conn, $this->remoteCwd);
        @ftp_close($conn);

        if ($connectionId !== null) {
            RemoteFtpConnection::where('id', $connectionId)->update(['last_connected_at' => now()]);
        }
    }

    private function storeSession(RemoteFtpConnection $connection, string $password): void
    {
        session([
            'remote_ftp' => [
                'id'           => $connection->id ?? null,
                'host'         => $connection->host,
                'port'         => (int) $connection->port,
                'protocol'     => $connection->protocol,
                'username'     => $connection->username,
                'passive'      => $connection->passive !== false,
                'initial_path' => $connection->initial_path ?? '/',
                'password'     => Crypt::encryptString($password),
            ],
        ]);
    }

    /**
     * Reconnect using the session descriptor.
     *
     * @return resource
     */
    private function reconnectIfNeeded(RemoteFtpService $ftp)
    {
        $descriptor = session('remote_ftp');
        if (!is_array($descriptor) || empty($descriptor['host'])) {
            throw new \RuntimeException('No hay una sesión FTP activa.');
        }

        try {
            $password = Crypt::decryptString($descriptor['password']);
        } catch (DecryptException) {
            throw new \RuntimeException('No se pudo recuperar la contraseña de la sesión.');
        }

        $connection = new RemoteFtpConnection([
            'host'         => $descriptor['host'],
            'port'         => (int) ($descriptor['port'] ?? 21),
            'protocol'     => $descriptor['protocol'] ?? 'ftp',
            'username'     => $descriptor['username'],
            'passive'      => (bool) ($descriptor['passive'] ?? true),
            'initial_path' => $descriptor['initial_path'] ?? '/',
        ]);

        return $ftp->connect($connection, $password);
    }

    public function disconnect(): void
    {
        session()->forget('remote_ftp');
        $this->connected = false;
        $this->activeConnectionId = null;
        $this->remoteCwd = '/';
        $this->listing = [];
        $this->successMessage = 'Sesión remota cerrada.';
    }

    // ─────────────────────────── navigation ───────────────────────────

    public function openRemote(string $dir, RemoteFtpService $ftp): void
    {
        $dir = (string) base64_decode($dir);
        $this->clearMessages();
        try {
            $conn = $this->reconnectIfNeeded($ftp);
            $this->remoteCwd = '/' . trim($dir, '/');
            $this->listing = $ftp->list($conn, $this->remoteCwd);
            @ftp_close($conn);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function goUp(RemoteFtpService $ftp): void
    {
        if ($this->remoteCwd === '/' || $this->remoteCwd === '') {
            return;
        }
        $parent = dirname($this->remoteCwd) ?: '/';
        $this->openRemote($parent, $ftp);
    }

    public function refreshListing(RemoteFtpService $ftp): void
    {
        $this->clearMessages();
        $this->openRemote($this->remoteCwd, $ftp);
    }

    // ─────────────────────────── copy ───────────────────────────

    public function askCopy(string $pathB64, string $type): void
    {
        $this->clearMessages();
        $remotePath = (string) base64_decode($pathB64);

        $this->copyItem = [
            'remote' => $remotePath,
            'type'   => $type === 'dir' ? 'dir' : 'file',
            'name'   => basename($remotePath),
        ];

        $base = $this->defaultTargetPath();
        $this->copyTarget = $base !== '' ? rtrim($base, '/') . '/' . basename($remotePath) : '';
    }

    public function closeCopyModal(): void
    {
        $this->copyItem = null;
        $this->copyTarget = '';
    }

    public function confirmCopy(RemoteFtpService $ftp): void
    {
        $this->clearMessages();
        $item = $this->copyItem;
        if (!$item) {
            return;
        }

        $target = rtrim(trim($this->copyTarget), '/');
        if ($target === '' || !str_starts_with($target, '/')) {
            $this->copyTarget = $target;
            $this->errorMessage = 'Ingrese una ruta de destino absoluta (ej. /var/www/midominio).';
            return;
        }

        try {
            if ($item['type'] === 'dir') {
                $this->startCopyDir($item['remote'], $ftp, $target);
            } else {
                $this->copyFile($item['remote'], $ftp, rtrim(dirname($target), '/'), basename($target));
            }
            $this->copyItem = null;
            $this->copyTarget = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function copyFile(string $remotePath, RemoteFtpService $ftp, ?string $targetDir = null, ?string $targetName = null): void
    {
        $targetDir = $targetDir ?? $this->resolvedTarget();
        $targetName = $targetName ?? basename($remotePath);

        try {
            $conn = $this->reconnectIfNeeded($ftp);

            $staging = storage_path('app/remote-ftp-staging');
            $staged = $ftp->downloadFile($conn, $remotePath, $staging);
            @ftp_close($conn);

            $ftp->moveInto($staged, $targetDir, $targetName);

            $this->successMessage = "Archivo '{$targetName}' copiado a {$targetDir}.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function startCopyDir(string $remotePath, RemoteFtpService $ftp, ?string $target = null): void
    {
        $target = $target ?? $this->resolvedTarget();

        try {
            $descriptor = session('remote_ftp');
            if (!is_array($descriptor) || empty($descriptor['host'])) {
                throw new \RuntimeException('No hay una sesión FTP activa.');
            }

            $password = Crypt::decryptString($descriptor['password']);

            $connection = new RemoteFtpConnection([
                'host'     => $descriptor['host'],
                'port'     => (int) ($descriptor['port'] ?? 21),
                'protocol' => $descriptor['protocol'] ?? 'ftp',
                'username' => $descriptor['username'],
                'passive'  => (bool) ($descriptor['passive'] ?? true),
            ]);

            $jobId = $ftp->startMirrorJob($connection, $remotePath, $target, $password);
            $this->selectedJob = $jobId;
            $this->successMessage = "Copia iniciada de '{$remotePath}' hacia {$target}.";
            $this->refreshJobs($ftp);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    private function resolvedTarget(): string
    {
        if ($this->targetDomainId) {
            $domain = Domain::where('user_id', auth()->id())->find($this->targetDomainId);
            if ($domain) {
                return '/var/www/' . $domain->name;
            }
        }

        $path = trim($this->targetPath);
        if ($path === '' || !str_starts_with($path, '/')) {
            throw new \RuntimeException('Seleccione un dominio de destino o escriba una ruta absoluta.');
        }
        return $path;
    }

    private function defaultTargetPath(): string
    {
        if ($this->targetDomainId) {
            $domain = Domain::where('user_id', auth()->id())->find($this->targetDomainId);
            if ($domain) {
                return '/var/www/' . $domain->name;
            }
        }

        $path = trim($this->targetPath);
        if ($path !== '' && str_starts_with($path, '/')) {
            return rtrim($path, '/');
        }

        $domain = auth()->user()?->domains()->orderBy('name')->first();
        return $domain ? '/var/www/' . $domain->name : '';
    }

    // ─────────────────────────── jobs ───────────────────────────

    public function refreshJobs(?RemoteFtpService $ftp = null): void
    {
        $ftp ??= app(RemoteFtpService::class);
        $this->jobs = $ftp->jobs();

        if ($this->selectedJob && !collect($this->jobs)->contains('id', $this->selectedJob)) {
            $this->selectedJob = null;
            $this->jobLog = '';
        }
    }

    public function showJob(string $jobId, RemoteFtpService $ftp): void
    {
        $this->selectedJob = $jobId;
        $this->jobLog = implode("\n", $ftp->tailJobLog($jobId, 120)['lines']);
    }

    public function deleteJob(string $jobId, RemoteFtpService $ftp): void
    {
        $ftp->deleteJob($jobId);
        if ($this->selectedJob === $jobId) {
            $this->selectedJob = null;
            $this->jobLog = '';
        }
        $this->refreshJobs($ftp);
    }

    // ─────────────────────────── connections ───────────────────────────

    public function deleteConnection(int $id): void
    {
        RemoteFtpConnection::where('user_id', auth()->id())->where('id', $id)->delete();
        if ($this->activeConnectionId === $id) {
            $this->disconnect();
        }
        $this->successMessage = 'Conexión remota eliminada.';
    }

    private function clearMessages(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }
}