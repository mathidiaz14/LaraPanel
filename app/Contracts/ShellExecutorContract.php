<?php

namespace App\Contracts;

use App\Shell\ShellResult;

/**
 * Contract for a secure shell command executor.
 *
 * Mirrors the public API of App\Shell\ShellExecutor so the concrete
 * implementation can be swapped for a fake in tests.
 */
interface ShellExecutorContract
{
    /**
     * Execute a command safely (no shell, no injection risk).
     *
     * @param  array  $command     Command as array: ['nginx', '-t']
     * @param  bool   $checkExit   Throw on non-zero exit code
     */
    public function run(array $command, bool $checkExit = true): ShellResult;

    /**
     * Execute command and stream output line by line via callback.
     */
    public function runStreaming(array $command, callable $onOutput): ShellResult;

    /**
     * Return a clone configured with the given timeout (seconds).
     */
    public function withTimeout(int $seconds): static;

    /**
     * Return a clone with additional environment variables merged in.
     */
    public function withEnv(array $env): static;

    /**
     * Return a clone that pipes the given input into the command.
     */
    public function withInput(string $input): static;

    /**
     * Return a clone that runs the command in the given working directory.
     */
    public function inDirectory(string $path): static;
}
