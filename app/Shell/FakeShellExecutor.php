<?php

namespace App\Shell;

/**
 * Test double for ShellExecutorContract.
 *
 * Records every command it is asked to run and returns a configurable
 * ShellResult (default: success, empty output). Fluent setters return
 * clones so callers can assert on the configuration they were given.
 */
class FakeShellExecutor implements ShellExecutorContract
{
    /** @var array<int, array<int, string>> */
    public array $recordedCommands = [];

    /** @var array<int, array{timeout:int, cwd:?string, env:array, input:mixed}> */
    public array $configurations = [];

    protected ShellResult $result;

    protected int $timeout = 60;
    protected ?string $workingDirectory = null;
    protected array $envVars = [];
    protected mixed $input = null;

    public function __construct(?ShellResult $result = null)
    {
        $this->result = $result ?? new ShellResult(0, '', '', '');
    }

    public function run(array $command, bool $checkExit = true): ShellResult
    {
        $this->recordedCommands[] = $command;

        if ($checkExit && !$this->result->successful()) {
            throw new \RuntimeException(
                "Fake command failed [{$this->result->command}]: {$this->result->stderr}"
            );
        }

        return $this->result;
    }

    public function runStreaming(array $command, callable $onOutput): ShellResult
    {
        $this->recordedCommands[] = $command;

        return $this->result;
    }

    public function withTimeout(int $seconds): static
    {
        $clone = clone $this;
        $clone->timeout = $seconds;
        $clone->recordConfig();

        return $clone;
    }

    public function withEnv(array $env): static
    {
        $clone = clone $this;
        $clone->envVars = array_merge($this->envVars, $env);
        $clone->recordConfig();

        return $clone;
    }

    public function withInput(string $input): static
    {
        $clone = clone $this;
        $clone->input = $input;
        $clone->recordConfig();

        return $clone;
    }

    public function inDirectory(string $path): static
    {
        $clone = clone $this;
        $clone->workingDirectory = $path;
        $clone->recordConfig();

        return $clone;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    public function getEnvVars(): array
    {
        return $this->envVars;
    }

    public function getInput(): mixed
    {
        return $this->input;
    }

    /**
     * Configure the result returned by run()/runStreaming().
     */
    public function setResult(ShellResult $result): static
    {
        $this->result = $result;

        return $this;
    }

    /**
     * Whether a command equal to $command was recorded.
     */
    public function ran(array $command): bool
    {
        return in_array($command, $this->recordedCommands, true);
    }

    /**
     * The most recent recorded command, or null.
     */
    public function lastCommand(): ?array
    {
        return empty($this->recordedCommands)
            ? null
            : end($this->recordedCommands);
    }

    protected function recordConfig(): void
    {
        $this->configurations[] = [
            'timeout' => $this->timeout,
            'cwd'     => $this->workingDirectory,
            'env'     => $this->envVars,
            'input'   => $this->input,
        ];
    }
}
