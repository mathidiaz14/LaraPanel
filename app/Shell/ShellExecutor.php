<?php

namespace App\Shell;

use Illuminate\Support\Facades\Log;
use App\Models\AuditLog;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * ShellExecutor — Secure wrapper for system command execution.
 *
 * Rules:
 * - Never use shell_exec, exec, passthru directly.
 * - All commands go through Symfony Process (no shell interpolation).
 * - Every execution is logged to the audit log.
 * - Commands can be whitelisted via config/larapanel.php.
 */
class ShellExecutor
{
    protected int $timeout = 60;
    protected ?string $workingDirectory = null;
    protected array $envVars = [];

    /**
     * Execute a command safely (no shell, no injection risk).
     *
     * @param  array  $command  Command as array: ['nginx', '-t']
     * @param  bool   $checkExit  Throw on non-zero exit code
     * @return ShellResult
     */
    public function run(array $command, bool $checkExit = true): ShellResult
    {
        $this->validateCommand($command);

        $process = new Process(
            command: $command,
            cwd: $this->workingDirectory,
            env: $this->envVars ?: null,
            timeout: $this->timeout,
        );

        $this->auditBefore($command);

        $process->run();

        $result = new ShellResult(
            exitCode: $process->getExitCode(),
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            command: implode(' ', $command),
        );

        $this->auditAfter($result);

        if ($checkExit && !$result->successful()) {
            throw new \RuntimeException(
                "Command failed [{$result->command}]: {$result->stderr}"
            );
        }

        return $result;
    }

    /**
     * Execute command and return output line by line via callback (for streaming).
     */
    public function runStreaming(array $command, callable $onOutput): ShellResult
    {
        $this->validateCommand($command);

        $process = new Process($command, $this->workingDirectory, null, null, $this->timeout);
        $stdout = '';
        $stderr = '';

        $this->auditBefore($command);

        $process->run(function (string $type, string $buffer) use (&$stdout, &$stderr, $onOutput) {
            if ($type === Process::OUT) {
                $stdout .= $buffer;
                $onOutput('stdout', $buffer);
            } else {
                $stderr .= $buffer;
                $onOutput('stderr', $buffer);
            }
        });

        $result = new ShellResult($process->getExitCode(), $stdout, $stderr, implode(' ', $command));
        $this->auditAfter($result);

        return $result;
    }

    public function withTimeout(int $seconds): static
    {
        $clone = clone $this;
        $clone->timeout = $seconds;
        return $clone;
    }

    public function inDirectory(string $path): static
    {
        $clone = clone $this;
        $clone->workingDirectory = $path;
        return $clone;
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
            while (isset($command[$cmdIndex]) && str_starts_with($command[$cmdIndex], '-')) {
                if ($command[$cmdIndex] === '-u' && isset($command[$cmdIndex + 1])) {
                    $cmdIndex += 2;
                } else {
                    $cmdIndex++;
                }
            }
        }

        $binary = isset($command[$cmdIndex]) ? basename($command[$cmdIndex]) : '';
        $allowed = config('larapanel.security.allowed_sudo_commands', []);

        // In local development, skip whitelist enforcement
        if (!app()->isProduction()) {
            return;
        }

        if (!in_array($binary, $allowed, true)) {
            Log::critical('LaraPanel: Blocked unauthorized command', ['command' => $command]);
            throw new \RuntimeException("Unauthorized command: [{$binary}]");
        }
    }

    protected function auditBefore(array $command): void
    {
        Log::channel('single')->info('ShellExecutor:run', [
            'cmd' => implode(' ', $command),
            'user' => auth()->id(),
        ]);
    }

    protected function auditAfter(ShellResult $result): void
    {
        Log::channel('single')->info('ShellExecutor:result', [
            'cmd'      => $result->command,
            'exit'     => $result->exitCode,
            'success'  => $result->successful(),
        ]);

        // Persist to DB audit log (non-blocking, best-effort)
        try {
            AuditLog::record(
                action: 'shell.command',
                subject: $result->command,
                meta: [
                    'exit_code' => $result->exitCode,
                    'success'   => $result->successful(),
                    'stderr'    => substr($result->stderr, 0, 500),
                ]
            );
        } catch (\Throwable) {
            // DB might not be available during install
        }
    }
}
