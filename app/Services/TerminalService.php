<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use App\Shell\SudoExecutor;

class TerminalService
{
    public function __construct(
        protected SudoExecutor $sudo
    ) {}

    /**
     * Executes a command and returns the output and exit code.
     * Keeps track of working directory changes (cd).
     */
    public function execute(string $command, string $cwd = '/var/www'): array
    {
        if (!app()->isProduction()) {
            return $this->getSimulatedOutput($command, $cwd);
        }

        // Special handling for 'cd' command to update state
        if (preg_match('/^cd\s+(.+)$/', trim($command), $matches)) {
            $path = $matches[1];
            // Resolve path safely (relative to cwd or absolute)
            $resolveCmd = "cd {$cwd} && cd {$path} 2>/dev/null && pwd";
            $result = Process::run($resolveCmd);
            
            if ($result->successful()) {
                return [
                    'output' => '',
                    'cwd'    => trim($result->output()),
                    'code'   => 0
                ];
            } else {
                return [
                    'output' => "cd: no such file or directory: {$path}\n",
                    'cwd'    => $cwd,
                    'code'   => 1
                ];
            }
        }

        try {
            // Run normal command in the specified directory
            // We use standard Process here instead of SudoExecutor for safety, 
            // but we can prepend sudo if needed based on rules.
            $result = Process::path($cwd)
                ->timeout(60) // 1 min max to prevent hanging
                ->run($command);

            return [
                'output' => $result->output() . $result->errorOutput(),
                'cwd'    => $cwd,
                'code'   => $result->exitCode()
            ];
        } catch (\Throwable $e) {
            return [
                'output' => "Error executing command: " . $e->getMessage() . "\n",
                'cwd'    => $cwd,
                'code'   => 127
            ];
        }
    }

    protected function getSimulatedOutput(string $command, string $cwd): array
    {
        $cmd = trim($command);
        
        if (preg_match('/^cd\s+(.+)$/', $cmd, $matches)) {
            $path = $matches[1];
            if ($path === '..') {
                $cwd = dirname($cwd);
            } elseif (str_starts_with($path, '/')) {
                $cwd = $path;
            } else {
                $cwd = rtrim($cwd, '/') . '/' . $path;
            }
            return ['output' => '', 'cwd' => $cwd, 'code' => 0];
        }

        if ($cmd === 'ls' || $cmd === 'ls -la') {
            $out = "total 24\n";
            $out .= "drwxr-xr-x 2 root root 4096 " . date('M d H:i') . " .\n";
            $out .= "drwxr-xr-x 3 root root 4096 " . date('M d H:i') . " ..\n";
            $out .= "-rw-r--r-- 1 root root  220 " . date('M d H:i') . " .bash_logout\n";
            $out .= "-rw-r--r-- 1 root root 3771 " . date('M d H:i') . " .bashrc\n";
            $out .= "-rw-r--r-- 1 root root  807 " . date('M d H:i') . " .profile\n";
            return ['output' => $out, 'cwd' => $cwd, 'code' => 0];
        }

        if ($cmd === 'pwd') {
            return ['output' => $cwd . "\n", 'cwd' => $cwd, 'code' => 0];
        }

        if ($cmd === 'whoami') {
            return ['output' => "root\n", 'cwd' => $cwd, 'code' => 0];
        }
        
        if (preg_match('/^(htop|nano|vim|top)$/', $cmd)) {
            return ['output' => "Error: Comando interactivo '{$cmd}' no soportado en la terminal web básica.\n", 'cwd' => $cwd, 'code' => 1];
        }

        return [
            'output' => "bash: {$cmd}: command not found (simulated)\n",
            'cwd'    => $cwd,
            'code'   => 127
        ];
    }
}
