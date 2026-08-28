<?php

namespace App\Shell;

use App\Models\Server;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use Illuminate\Support\Facades\Log;

/**
 * RemoteShellExecutor — Executes commands on a remote server via SSH.
 *
 * Implements the same fluent API as ShellExecutor so all services
 * can work transparently against local or remote servers.
 */
class RemoteShellExecutor
{
    protected int     $timeout          = 60;
    protected ?string $workingDirectory = null;
    protected array   $envVars          = [];
    protected ?SSH2   $connection       = null;

    public function __construct(
        protected Server $server
    ) {}

    // ── Fluent API ────────────────────────────────────────────────────────────

    public function withTimeout(int $seconds): static
    {
        $clone          = clone $this;
        $clone->timeout = $seconds;
        return $clone;
    }

    public function inDirectory(string $path): static
    {
        $clone                    = clone $this;
        $clone->workingDirectory  = $path;
        return $clone;
    }

    public function withEnv(array $env): static
    {
        $clone          = clone $this;
        $clone->envVars = array_merge($this->envVars, $env);
        return $clone;
    }

    // ── Core execution ────────────────────────────────────────────────────────

    /**
     * Execute a command array on the remote server.
     *
     * @param  array $command  e.g. ['docker', 'ps', '-a']
     * @param  bool  $checkExit  Throw if exit code != 0
     */
    public function run(array $command, bool $checkExit = true): ShellResult
    {
        $this->validateCommand($command);

        $ssh = $this->getConnection();

        // Build safe command string (no shell interpolation from array)
        $cmdParts = array_map('escapeshellarg', $command);
        $cmdString = implode(' ', $cmdParts);

        if (!empty($this->envVars)) {
            $envPrefix = '';
            foreach ($this->envVars as $key => $val) {
                $envPrefix .= $key . '=' . escapeshellarg((string) $val) . ' ';
            }
            $cmdString = $envPrefix . $cmdString;
        }

        if ($this->workingDirectory) {
            $cmdString = 'cd ' . escapeshellarg($this->workingDirectory) . ' && ' . $cmdString;
        }

        Log::info('RemoteShellExecutor:run', [
            'server' => $this->server->hostname,
            'cmd'    => $cmdString,
            'user'   => auth()->id(),
        ]);

        $ssh->setTimeout($this->timeout);

        $output   = $ssh->exec($cmdString);
        $stderr   = $ssh->getStdError();
        $exitCode = $ssh->getExitStatus();

        // phpseclib returns false on connection error or execution failure
        if ($output === false) {
            throw new \RuntimeException(
                "SSH exec failed on [{$this->server->hostname}]: " . $ssh->getLastError()
            );
        }

        $codeInt = is_int($exitCode) ? $exitCode : 1;

        $result = new ShellResult(
            exitCode: $codeInt,
            stdout:   $output,
            stderr:   is_string($stderr) ? $stderr : '',
            command:  implode(' ', $command) . ' [remote: ' . $this->server->hostname . ']',
        );

        if ($checkExit && !$result->successful()) {
            throw new \RuntimeException(
                "Remote command failed [{$this->server->hostname}]: " . ($result->stderr ?: $result->stdout)
            );
        }

        // Mirror the local ShellExecutor audit trail for remote commands.
        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::record(
                'shell.remote',
                $cmdString,
                [
                    'server'   => $this->server->hostname,
                    'exit_code' => $result->exitCode,
                    'stdout'   => mb_substr($result->stdout, 0, 2000),
                    'stderr'   => mb_substr($result->stderr, 0, 2000),
                ],
                $result->successful() ? 'info' : 'warning',
            );
        }

        return $result;
    }

    /**
     * Validate that the base command is in the whitelist.
     */
    protected function validateCommand(array $command): void
    {
        if (empty($command)) {
            throw new \InvalidArgumentException('Command cannot be empty.');
        }

        $cmdIndex = 0;
        if ($command[0] === 'sudo') {
            $cmdIndex = 1;
            while (isset($command[$cmdIndex])) {
                if (str_starts_with($command[$cmdIndex], '-')) {
                    if ($command[$cmdIndex] === '-u' && isset($command[$cmdIndex + 1])) {
                        $cmdIndex += 2;
                    } else {
                        $cmdIndex++;
                    }
                    continue;
                }
                if (str_contains($command[$cmdIndex], '=')) {
                    $cmdIndex++;
                    continue;
                }
                break;
            }
        }

        $binary = isset($command[$cmdIndex]) ? basename($command[$cmdIndex]) : '';
        $allowed = config('larapanel.security.allowed_sudo_commands', []);

        if (!in_array($binary, $allowed, true)) {
            Log::critical('LaraPanel: Blocked unauthorized remote command', [
                'command' => $command,
                'server'  => $this->server->hostname,
            ]);
            throw new \RuntimeException("Unauthorized command: [{$binary}]");
        }
    }

    // ── SSH Connection ────────────────────────────────────────────────────────

    protected function getConnection(): SSH2
    {
        if ($this->connection && $this->connection->isConnected()) {
            return $this->connection;
        }

        $ssh = new SSH2($this->server->hostname, $this->server->port);
        $ssh->setTimeout($this->timeout);

        $authenticated = false;

        if ($this->server->auth_type === 'key' && $this->server->ssh_private_key) {
            try {
                $key           = PublicKeyLoader::load($this->server->ssh_private_key);
                $authenticated = $ssh->login($this->server->username, $key);
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    "SSH key authentication failed for [{$this->server->hostname}]: " . $e->getMessage()
                );
            }
        } elseif ($this->server->auth_type === 'password' && $this->server->ssh_password) {
            $authenticated = $ssh->login($this->server->username, $this->server->ssh_password);
        }

        if (!$authenticated) {
            throw new \RuntimeException(
                "SSH authentication failed for [{$this->server->hostname}]. Check credentials."
            );
        }

        $this->connection = $ssh;
        return $ssh;
    }

    /**
     * Test the SSH connection. Returns ['ok' => bool, 'latency_ms' => int, 'error' => string|null]
     */
    public function testConnection(): array
    {
        $start = microtime(true);
        try {
            $conn = $this->getConnection();
            $latency = (int) ((microtime(true) - $start) * 1000);
            return ['ok' => true, 'latency_ms' => $latency, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'latency_ms' => null, 'error' => $e->getMessage()];
        }
    }

    public function getServer(): Server
    {
        return $this->server;
    }
}
