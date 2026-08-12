<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\TerminalSession;
use Illuminate\Support\Facades\Process;
use Laravel\Reverb\Application;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Throwable;

/**
 * TerminalSessionManager — owns the interactive PTY sessions inside the
 * Reverb process (the only place PHP can keep a spawned shell alive).
 *
 * Each session maps to a single bash launched through `script`, which gives
 * the shell a real TTY while keeping plain pipes on our side. Output is read
 * by a periodic ReactPHP timer and pushed to the session's private channel;
 * keystrokes come back through client events handled by the socket listener.
 *
 * Linux-only: depends on `script` (util-linux) and `/proc`.
 */
class TerminalSessionManager
{
    /** @var array<int, array<string, mixed>> */
    protected array $sessions = [];

    protected ?TimerInterface $pump = null;

    protected float $pumpInterval = 0.05;

    protected int $chunkBytes = 4000;

    // ── Public API ───────────────────────────────────────────────────────────

    public function has(int $sessionId): bool
    {
        return isset($this->sessions[$sessionId]);
    }

    /**
     * Spawn the PTY for the given session and start streaming to its channel.
     */
    public function attach(TerminalSession $session, Application $app): void
    {
        if ($this->has($session->id)) {
            return;
        }

        $runtime = [
            'session_id' => $session->id,
            'type' => $session->type,
            'channel' => $session->channelName(),
            'app' => $app,
            'proc' => null,
            'pipes' => [],
            'child_pid' => null,
            'pty_path' => null,
            'temp_key' => null,
            'buffer' => '',
            'last_activity' => microtime(true),
        ];

        if (! $this->spawn($session, $runtime)) {
            $this->broadcast($runtime, 'terminal-error', ['message' => 'No se pudo abrir el shell interactivo (requiere `script` en el servidor).']);
            $session->close('spawn failed');

            return;
        }

        $this->sessions[$session->id] = $runtime;
        $this->ensurePump();

        $session->forceFill([
            'status' => TerminalSession::STATUS_ATTACHED,
            'last_activity_at' => now(),
        ])->save();

        $this->broadcast($runtime, 'terminal-attached', [
            'type' => $session->type,
            'cwd' => $session->cwd,
            'server' => $session->type === 'ssh' && $session->server ? $session->server->name : null,
        ]);

        AuditLog::record('terminal.session.attach', "terminal:{$session->type}", [
            'session_id' => $session->id,
            'channel' => $session->channel,
        ]);
    }

    /**
     * Forward keystrokes to the shell stdin.
     */
    public function write(int $sessionId, string $bytes): bool
    {
        $runtime = $this->sessions[$sessionId] ?? null;

        if (! $runtime || ! is_resource($runtime['pipes'][0] ?? null)) {
            return false;
        }

        @fwrite($runtime['pipes'][0], $bytes);

        $runtime['last_activity'] = microtime(true);
        $this->sessions[$sessionId] = $runtime;

        $this->auditInput($sessionId, $bytes);

        return true;
    }

    /**
     * Update the terminal size; best-effort via `stty` on the discovered pty.
     */
    public function resize(int $sessionId, int $cols, int $rows): void
    {
        $runtime = $this->sessions[$sessionId] ?? null;

        if (! $runtime) {
            return;
        }

        $runtime['last_activity'] = microtime(true);
        $this->sessions[$sessionId] = $runtime;

        if ($cols > 0 && $rows > 0) {
            $this->resizePty($runtime, $cols, $rows);
        }

        AuditLog::record('terminal.session.resize', "terminal:{$runtime['type']}", [
            'session_id' => $sessionId,
            'cols' => $cols,
            'rows' => $rows,
        ]);
    }

    /**
     * Forcefully terminate the shell process tree.
     */
    public function kill(int $sessionId): void
    {
        $runtime = $this->sessions[$sessionId] ?? null;

        if (! $runtime) {
            $this->closeDatabaseSession($sessionId);

            return;
        }

        $this->groupKill($runtime);
        $this->finalize($sessionId, 137);
    }

    /**
     * Tear down a session without notifying listeners (disconnect / GC).
     */
    public function close(int $sessionId): void
    {
        $runtime = $this->sessions[$sessionId] ?? null;

        if ($runtime) {
            $this->teardown($runtime);
            unset($this->sessions[$sessionId]);
        }

        $this->closeDatabaseSession($sessionId);
        $this->stopPumpIfIdle();
    }

    // ── Process lifecycle ─────────────────────────────────────────────────────

    protected function spawn(TerminalSession $session, array &$runtime): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        try {
            $environment = $this->buildEnv();

            if ($session->type === 'ssh') {
                $server = $session->server;

                if (! $server || ! $server->is_active || $server->is_local) {
                    return false;
                }

                $shell = $this->buildSshCommand($server, $runtime, $environment);
            } else {
                $shell = config('larapanel.security.terminal.allow_sudo_root', false)
                    ? 'sudo -n bash'
                    : 'bash';
            }

            $command = $this->buildShellCommand($shell, $session->type);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $cwd = $session->cwd ?: config('larapanel.security.terminal.default_cwd', '/var/www');
            if (! is_dir($cwd) && PHP_OS_FAMILY === 'Windows') {
                $cwd = base_path();
            }
            $proc = proc_open($command, $descriptors, $pipes, $cwd, $environment);
        } catch (Throwable) {
            if (is_string($runtime['temp_key'] ?? null) && is_file($runtime['temp_key'])) {
                @unlink($runtime['temp_key']);
                $runtime['temp_key'] = null;
            }

            return false;
        }

        if (! is_resource($proc)) {
            return false;
        }

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $runtime['proc'] = $proc;
        $runtime['pipes'] = $pipes;

        $this->discoverPty($runtime);

        return true;
    }

    protected function buildShellCommand(string $shell, string $type): string
    {
        if (PHP_OS_FAMILY === 'Windows' && $type === 'local') {
            // Development fallback only; Linux uses the real `script` PTY.
            return 'bash.exe -i';
        }

        return 'script -qefc '.escapeshellarg($shell).' /dev/null';
    }

    protected function buildSshCommand(Server $server, array &$runtime, array &$environment): string
    {
        $arguments = [
            'ssh', '-tt',
            '-o', 'ConnectTimeout=10',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-p', (string) $server->port,
        ];

        if ($server->auth_type === 'key') {
            $key = $server->ssh_private_key;

            if (! is_string($key) || trim($key) === '') {
                throw new \RuntimeException('El servidor remoto no tiene una clave SSH configurada.');
            }

            $directory = storage_path('app/terminal-keys');
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new \RuntimeException('No se pudo crear el directorio temporal de claves SSH.');
            }

            $path = $directory.'/session-'.bin2hex(random_bytes(16));
            if (file_put_contents($path, rtrim($key)."\n") === false) {
                throw new \RuntimeException('No se pudo guardar temporalmente la clave SSH.');
            }

            chmod($path, 0600);
            $runtime['temp_key'] = $path;
            $arguments[] = '-o';
            $arguments[] = 'BatchMode=yes';
            $arguments[] = '-i';
            $arguments[] = $path;
        } elseif ($server->auth_type === 'password') {
            if (! config('larapanel.security.terminal.sshpass_enabled', false)) {
                throw new \RuntimeException('La autenticación SSH por password está deshabilitada.');
            }

            $password = $server->ssh_password;
            if (! is_string($password) || $password === '') {
                throw new \RuntimeException('El servidor remoto no tiene un password SSH configurado.');
            }

            $environment['SSHPASS'] = $password;
            array_unshift($arguments, 'sshpass', '-e');
        } else {
            throw new \RuntimeException('Tipo de autenticación SSH no soportado.');
        }

        $arguments[] = $server->username.'@'.$server->hostname;

        return implode(' ', array_map('escapeshellarg', $arguments));
    }

    protected function buildEnv(): array
    {
        $env = [
            'TERM' => 'xterm-256color',
            'LANG' => 'C.UTF-8',
            'HOME' => (string) config('larapanel.server.sudo_user', 'www-data'),
        ];

        if (is_string($path = getenv('PATH'))) {
            $env['PATH'] = $path;
        }

        return $env;
    }

    protected function pump(): void
    {
        foreach (array_keys($this->sessions) as $id) {
            $this->drain($id);
        }

        $this->gc();
    }

    protected function drain(int $id): void
    {
        $runtime = $this->sessions[$id] ?? null;

        if (! $runtime || ! is_resource($runtime['proc'] ?? null)) {
            return;
        }

        // Best effort: resolve the pty path once bash has started.
        if (! $runtime['pty_path']) {
            $this->discoverPty($runtime);
            $this->sessions[$id] = $runtime;
        }

        $output = '';

        foreach ([1, 2] as $fd) {
            $pipe = $runtime['pipes'][$fd] ?? null;

            if (! is_resource($pipe)) {
                continue;
            }

            while (($data = fread($pipe, 16384)) !== '' && $data !== false) {
                $output .= $data;
            }
        }

        if ($output !== '') {
            $runtime['last_activity'] = microtime(true);
            $this->sessions[$id] = $runtime;
            $this->sendChunks($runtime, $output);
        }

        $status = proc_get_status($runtime['proc']);

        if (! $status['running']) {
            $this->finalize($id, (int) $status['exitcode']);
        }
    }

    protected function finalize(int $id, int $code): void
    {
        $runtime = $this->sessions[$id] ?? null;

        if ($runtime) {
            $this->broadcast($runtime, 'terminal-exit', ['code' => $code]);

            if ($this->pump) {
                $content = '';
                foreach ([1, 2] as $fd) {
                    $pipe = $runtime['pipes'][$fd] ?? null;
                    if (is_resource($pipe)) {
                        $content .= (string) stream_get_contents($pipe);
                    }
                }
                if ($content !== '') {
                    $this->sendChunks($runtime, $content);
                }
            }

            $this->teardown($runtime);
            unset($this->sessions[$id]);
        }

        $this->closeDatabaseSession($id);

        $type = $runtime['type'] ?? 'unknown';

        AuditLog::record('terminal.session.exit', "terminal:{$type}", [
            'session_id' => $id,
            'code' => $code,
        ]);

        $this->stopPumpIfIdle();
    }

    protected function teardown(array $runtime): void
    {
        foreach ($runtime['pipes'] as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }

        if (is_resource($runtime['proc'] ?? null)) {
            proc_terminate($runtime['proc'], 9);
            @proc_close($runtime['proc']);
        }

        if (is_string($runtime['temp_key'] ?? null) && is_file($runtime['temp_key'])) {
            @unlink($runtime['temp_key']);
        }
    }

    protected function groupKill(array $runtime): void
    {
        $status = proc_get_status($runtime['proc']);
        $pid = (int) ($status['pid'] ?? 0);

        if ($pid <= 0) {
            return;
        }

        try {
            @posix_kill(-$pid, 9);
        } catch (Throwable) {
            // posix extension may be missing; fall back to proc_terminate
        }

        if (! is_resource($runtime['proc'] ?? null) || proc_get_status($runtime['proc'])['running']) {
            if (is_resource($runtime['proc'] ?? null)) {
                proc_terminate($runtime['proc'], 9);
            }
        }
    }

    // ── Output streaming ──────────────────────────────────────────────────────

    protected function sendChunks(array $runtime, string $content): void
    {
        foreach (str_split($content, $this->chunkBytes) as $chunk) {
            $this->broadcast($runtime, 'terminal-output', [
                'b64' => base64_encode($chunk),
            ]);
        }
    }

    protected function broadcast(array $runtime, string $event, array $data): void
    {
        try {
            $channel = app(ChannelManager::class)
                ->for($runtime['app'])
                ->find($runtime['channel']);
        } catch (Throwable) {
            return;
        }

        if (! $channel) {
            return;
        }

        $channel->broadcastToAll([
            'event' => $event,
            'channel' => $runtime['channel'],
            'data' => json_encode($data, JSON_UNESCAPED_SLASHES),
        ]);
    }

    // ── PTY discovery + resize ────────────────────────────────────────────────

    protected function discoverPty(array &$runtime): void
    {
        $status = proc_get_status($runtime['proc']);

        $scriptPid = (int) ($status['pid'] ?? 0);
        if ($scriptPid <= 0) {
            return;
        }

        $children = trim((string) @file_get_contents("/proc/{$scriptPid}/task/{$scriptPid}/children"));

        foreach ($children === '' ? [] : preg_split('/\s+/', $children) as $pid) {
            if (! $pid) {
                continue;
            }

            $target = @readlink("/proc/{$pid}/fd/0");

            if ($target && str_starts_with($target, '/dev/pts/')) {
                $runtime['child_pid'] = (int) $pid;
                $runtime['pty_path'] = $target;

                return;
            }

            if ($runtime['child_pid'] === null) {
                $runtime['child_pid'] = (int) $pid;
            }
        }
    }

    protected function resizePty(array $runtime, int $cols, int $rows): void
    {
        if (! $runtime['pty_path']) {
            return;
        }

        try {
            Process::timeout(2)->run(
                'stty -F '.escapeshellarg($runtime['pty_path'])
                .' rows '.(int) $rows.' cols '.(int) $cols.' 2>/dev/null'
            );
        } catch (Throwable) {
            // resizing is best-effort
        }
    }

    // ── Idle GC ───────────────────────────────────────────────────────────────

    protected function gc(): void
    {
        $timeout = (float) config('larapanel.security.terminal.idle_timeout_minutes', 30) * 60;
        $now = microtime(true);

        foreach ($this->sessions as $id => $runtime) {
            if ($now - $runtime['last_activity'] > $timeout) {
                $this->kill($id);

                AuditLog::record('terminal.session.idle_timeout', "terminal:{$runtime['type']}", [
                    'session_id' => $id,
                ]);
            }
        }
    }

    protected function ensurePump(): void
    {
        if ($this->pump instanceof TimerInterface) {
            return;
        }

        $this->pump = Loop::addPeriodicTimer($this->pumpInterval, function () {
            $this->pump();
        });
    }

    protected function stopPumpIfIdle(): void
    {
        if ($this->pump instanceof TimerInterface && $this->sessions === []) {
            Loop::cancelTimer($this->pump);
            $this->pump = null;
        }
    }

    // ── Audit helpers ─────────────────────────────────────────────────────────

    protected function auditInput(int $sessionId, string $bytes): void
    {
        if (! isset($this->sessions[$sessionId])) {
            return;
        }

        $this->sessions[$sessionId]['buffer'] .= $bytes;

        $buffer = $this->sessions[$sessionId]['buffer'];

        while (preg_match('/\r\n|\r|\n/', $buffer, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $line = substr($buffer, 0, $offset);
            $buffer = substr($buffer, $offset + strlen($match[0][0]));

            $this->auditLine($sessionId, $line);
        }

        if (strlen($buffer) > 8192) {
            $buffer = substr($buffer, -4096);
        }

        $this->sessions[$sessionId]['buffer'] = $buffer;
    }

    protected function auditLine(int $sessionId, string $line): void
    {
        $command = self::sanitizeCommandLine($line);

        if ($command === '' || $command === 'clear') {
            return;
        }

        if (preg_match('/[\x00-\x08\x0e-\x1f]/', $command)) {
            return;
        }

        AuditLog::record('terminal.command', $command, [
            'session_id' => $sessionId,
        ]);
    }

    public static function sanitizeCommandLine(string $line): string
    {
        $line = preg_replace('/\x1b\[[0-9;?]*[A-Za-z]/', '', $line) ?? '';
        $line = preg_replace('/[\x00-\x1f\x7f]/', '', $line) ?? '';

        return trim(mb_substr($line, 0, 512));
    }

    protected function closeDatabaseSession(int $sessionId): void
    {
        try {
            TerminalSession::where('id', $sessionId)
                ->where('status', '!=', TerminalSession::STATUS_CLOSED)
                ->update([
                    'status' => TerminalSession::STATUS_CLOSED,
                    'ended_at' => now(),
                ]);
        } catch (Throwable) {
            // best effort
        }
    }
}
