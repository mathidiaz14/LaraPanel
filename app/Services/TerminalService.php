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
     *
     * Security: only whitelisted base commands are allowed and shell
     * separators (;, |, &&, ||, `, $(), newline) are rejected.
     */
    public function execute(string $command, string $cwd = '/var/www'): array
    {
        if (!app()->isProduction()) {
            return $this->getSimulatedOutput($command, $cwd);
        }

        // Special handling for 'cd' command to update state
        if (preg_match('/^cd\s*(.*)$/', trim($command), $matches)) {
            $path = trim($matches[1]);
            if (empty($path)) {
                $path = '/var/www';
            }
            $candidate = str_starts_with($path, '/') ? $path : rtrim($cwd, '/') . '/' . $path;
            $resolved = realpath($candidate);

            if ($resolved !== false && is_dir($resolved)) {
                return [
                    'output' => '',
                    'cwd'    => $resolved,
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

        $cmd = trim($command);

        // Reject shell separators / command substitution to prevent chaining
        if (preg_match('/(;|\||&&|\|\||`|\$\(|\n)/', $cmd)) {
return [
                'output' => 'Comando bloqueado: no se permiten separadores de shell (; | && || ` $() ) ni saltos de linea.' . "\n",
                'cwd'    => $cwd,
                'code'   => 1
            ];
        }

        // Enforce base-command whitelist
        $base = $this->baseCommandOf($cmd);
        if ($base === null || !$this->isAllowedCommand($base)) {
            return [
                'output' => "Comando bloqueado: '{$base}' no está en la lista de comandos permitidos.\n",
                'cwd'    => $cwd,
                'code'   => 1
            ];
        }

        try {
            // Auto-append -y for non-interactive apt/apt-get installs if missing
            if (preg_match('/^apt(-get)?\s+install\b/', $cmd) && !str_contains($cmd, '-y')) {
                $cmd = preg_replace('/^apt(-get)?\s+install\b/', '$0 -y', $cmd);
            }

            // Administrative commands pattern for automatic sudo elevation
            $adminCommandsPattern = '/^(apt|apt-get|systemctl|service|ufw|certbot|useradd|userdel|docker|nginx|goaccess|clamscan|freshclam)\b/';

            if (str_starts_with($cmd, 'sudo ')) {
                // Ensure non-interactive sudo flag (-n)
                if (!str_starts_with($cmd, 'sudo -n ')) {
                    $cmd = 'sudo -n ' . substr($cmd, 5);
                }
            } elseif (preg_match($adminCommandsPattern, $cmd)) {
                // Automatically try running elevated admin commands with sudo -n
                $cmd = 'sudo -n ' . $cmd;
            }

            // The command is intentionally interpreted by bash for the legacy
            // single-command terminal; its base binary and syntax are checked above.
            $result = Process::path($cwd)
                ->timeout(120) // 2 min max to prevent hanging
                ->run(['bash', '-lc', $cmd]);

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

    /**
     * Extract the base binary from a command line (handles an optional
     * leading `sudo`, its flags and env assignments).
     */
    protected function baseCommandOf(string $cmd): ?string
    {
        $tokens = preg_split('/\s+/', trim($cmd)) ?: [];
        $i = 0;

        if (isset($tokens[$i]) && $tokens[$i] === 'sudo') {
            $i++;
        }

        while (isset($tokens[$i])) {
            $token = $tokens[$i];
            if ($token === '-u' && isset($tokens[$i + 1])) {
                $i += 2;
                continue;
            }
            if (str_starts_with($token, '-') || str_contains($token, '=')) {
                $i++;
                continue;
            }
            break;
        }

        return $tokens[$i] ?? null;
    }

    /**
     * Check whether the base command is in the terminal whitelist.
     */
    protected function isAllowedCommand(string $base): bool
    {
        $allowed = config('larapanel.security.allowed_terminal_commands', []);
        return in_array($base, $allowed, true);
    }

    protected function getSimulatedOutput(string $command, string $cwd): array
    {
        $cmd = trim($command);
        
        if (preg_match('/^cd\s*(.*)$/', $cmd, $matches)) {
            $path = trim($matches[1]);
            if (empty($path)) {
                $cwd = '/var/www';
            } elseif ($path === '..') {
                $cwd = dirname($cwd);
            } elseif (str_starts_with($path, '/')) {
                $cwd = $path;
            } else {
                $cwd = rtrim($cwd, '/') . '/' . $path;
            }
            return ['output' => '', 'cwd' => $cwd, 'code' => 0];
        }

        if ($cmd === 'ls' || $cmd === 'ls -la' || $cmd === 'ls -l') {
            $out = "total 32\n";
            $out .= "drwxr-xr-x 5 root root 4096 " . date('M d H:i') . " .\n";
            $out .= "drwxr-xr-x 3 root root 4096 " . date('M d H:i') . " ..\n";
            $out .= "-rw-r--r-- 1 root root  220 " . date('M d H:i') . " .bash_logout\n";
            $out .= "-rw-r--r-- 1 root root 3771 " . date('M d H:i') . " .bashrc\n";
            $out .= "-rw-r--r-- 1 root root  807 " . date('M d H:i') . " .profile\n";
            $out .= "drwxr-xr-x 2 root root 4096 " . date('M d H:i') . " html\n";
            return ['output' => $out, 'cwd' => $cwd, 'code' => 0];
        }

        if (preg_match('/^(sudo\s+)?apt(-get)?\s+install(\s+-y)?\s+(.+)$/', $cmd, $m)) {
            $pkg = $m[4];
            $out = "Reading package lists... Done\n";
            $out .= "Building dependency tree... Done\n";
            $out .= "Reading state information... Done\n";
            $out .= "The following NEW packages will be installed:\n  {$pkg}\n";
            $out .= "0 upgraded, 1 newly installed, 0 to remove and 0 not upgraded.\n";
            $out .= "Unpacking {$pkg} (simulated)...\n";
            $out .= "Setting up {$pkg} (simulated)...\n";
            $out .= "Processing triggers for man-db... Done\n";
            return ['output' => $out, 'cwd' => $cwd, 'code' => 0];
        }

        if (preg_match('/^(sudo\s+)?(apt|apt-get)\s+update/', $cmd)) {
            $out = "Hit:1 http://archive.ubuntu.com/ubuntu noble InRelease\n";
            $out .= "Get:2 http://archive.ubuntu.com/ubuntu noble-updates InRelease [126 kB]\n";
            $out .= "Reading package lists... Done\n";
            return ['output' => $out, 'cwd' => $cwd, 'code' => 0];
        }

        if ($cmd === 'pwd') {
            return ['output' => $cwd . "\n", 'cwd' => $cwd, 'code' => 0];
        }

        if ($cmd === 'whoami' || $cmd === 'sudo whoami') {
            return ['output' => "root\n", 'cwd' => $cwd, 'code' => 0];
        }

        if (preg_match('/^(sudo\s+)?systemctl\s+(status|restart|reload|start|stop)\s+(.+)$/', $cmd, $m)) {
            $action = $m[2];
            $svc = $m[3];
            if ($action === 'status') {
                $out = "● {$svc}.service - {$svc} Service\n   Loaded: loaded\n   Active: active (running) since " . date('Y-m-d H:i:s') . "\n";
            } else {
                $out = "Service {$svc} {$action}ed successfully (simulated).\n";
            }
            return ['output' => $out, 'cwd' => $cwd, 'code' => 0];
        }

        if (preg_match('/^(htop|nano|vim|top)$/', $cmd)) {
            return ['output' => "Error: Comando interactivo '{$cmd}' no soportado en la terminal web básica.\n", 'cwd' => $cwd, 'code' => 1];
        }

        return [
            'output' => "bash: {$cmd}: command executed (simulated)\n",
            'cwd'    => $cwd,
            'code'   => 0
        ];
    }
}
