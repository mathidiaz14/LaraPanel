<?php

namespace App\Shell;

/**
 * SudoExecutor — Runs commands as root via sudo.
 *
 * Uses ShellExecutor internally but prepends 'sudo' to the command.
 * The LaraPanel system user must have specific sudoers entries.
 *
 * /etc/sudoers.d/larapanel:
 *   www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx, /bin/systemctl, ...
 */
class SudoExecutor extends ShellExecutor
{
    public function run(array $command, bool $checkExit = true): ShellResult
    {
        $sudoCmd = ['sudo', '-n'];
        
        // sudo strips environment variables by default.
        // We explicitly pass them as VAR=value arguments to sudo.
        foreach ($this->envVars as $key => $value) {
            $sudoCmd[] = "{$key}={$value}";
        }

        return parent::run(array_merge($sudoCmd, $command), $checkExit);
    }

    /**
     * Restart a systemd service safely.
     */
    public function restartService(string $service): ShellResult
    {
        $allowed = ['nginx', 'apache2', 'mysql', 'redis-server', 'supervisor', 'fail2ban'];
        $isPhpFpm = preg_match('/^php\d+\.\d+-fpm$/', $service);

        if (!in_array($service, $allowed, true) && !$isPhpFpm) {
            throw new \InvalidArgumentException("Service [{$service}] is not in the allowed list.");
        }

        return $this->run(['systemctl', 'restart', $service]);
    }

    /**
     * Get the status of a systemd service.
     */
    public function serviceStatus(string $service): array
    {
        $result = $this->run(['systemctl', 'is-active', $service], checkExit: false);
        $active = trim($result->stdout) === 'active';

        return [
            'service' => $service,
            'active'  => $active,
            'status'  => trim($result->stdout),
        ];
    }

    /**
     * Reload nginx config without restart (zero-downtime).
     */
    public function reloadNginx(): ShellResult
    {
        // Test config first
        $test = $this->run(['nginx', '-t'], checkExit: false);
        if ($test->failed()) {
            throw new \RuntimeException("Nginx config test failed: {$test->stderr}");
        }

        return $this->run(['systemctl', 'reload', 'nginx']);
    }
}
