<?php

namespace App\Shell;

/**
 * Value object representing the result of a shell command execution.
 */
readonly class ShellResult
{
    public function __construct(
        public int    $exitCode,
        public string $stdout,
        public string $stderr,
        public string $command,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return !$this->successful();
    }

    public function output(): string
    {
        return trim($this->stdout);
    }

    public function lines(): array
    {
        return array_filter(explode("\n", trim($this->stdout)));
    }

    public function toArray(): array
    {
        return [
            'exit_code' => $this->exitCode,
            'stdout'    => $this->stdout,
            'stderr'    => $this->stderr,
            'command'   => $this->command,
            'success'   => $this->successful(),
        ];
    }
}
