<?php

namespace App\Services;

use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Auth;

class LogService
{
    public function __construct(
        protected SudoExecutor $sudo
    ) {}

    /**
     * Get available logs depending on user role.
     */
    public function getAvailableLogs(): array
    {
        $logs = [];

        // System/Admin logs
        if (Auth::user()?->isAdmin()) {
            $logs['laravel'] = [
                'name' => 'LaraPanel Core (laravel.log)',
                'path' => storage_path('logs/laravel.log'),
                'type' => 'panel'
            ];
            $logs['syslog'] = [
                'name' => 'System Log (syslog)',
                'path' => '/var/log/syslog',
                'type' => 'system'
            ];
            $logs['auth'] = [
                'name' => 'Auth Log (auth.log)',
                'path' => '/var/log/auth.log',
                'type' => 'system'
            ];
            $logs['nginx_error'] = [
                'name' => 'Nginx Global Error Log',
                'path' => '/var/log/nginx/error.log',
                'type' => 'service'
            ];
            $logs['fail2ban'] = [
                'name' => 'Fail2ban Log',
                'path' => '/var/log/fail2ban.log',
                'type' => 'service'
            ];
        }

        // Domain logs for current user (everyone)
        $domains = Auth::user()->domains()->get();
        foreach ($domains as $domain) {
            $logs['nginx_' . $domain->name] = [
                'name' => "Nginx Error: {$domain->name}",
                'path' => "/var/log/nginx/{$domain->name}.error.log",
                'type' => 'domain'
            ];
            $logs['php_' . $domain->name] = [
                'name' => "PHP-FPM Error: {$domain->name}",
                'path' => "/var/log/php-fpm/{$domain->name}.error.log",
                'type' => 'domain'
            ];
        }

        return $logs;
    }

    /**
     * Tail a specific log file
     */
    public function tailLog(string $path, int $lines = 100): string
    {
        // Prevent path traversal
        if (str_contains($path, '..')) {
            throw new \InvalidArgumentException("Invalid path.");
        }

        // Verify user has access to this log
        $available = $this->getAvailableLogs();
        $allowed = false;
        foreach ($available as $log) {
            if ($log['path'] === $path) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new \RuntimeException("Acceso denegado a este archivo de log.");
        }

        if (!file_exists($path)) {
            return "El archivo de log no existe o está vacío.";
        }

        // System logs usually require sudo to read (like auth.log)
        try {
            $output = $this->sudo->run(['tail', '-n', (string)$lines, $path]);
            return implode("\n", $output);
        } catch (\Throwable $e) {
            // Fallback for non-sudo or local environment
            if (is_readable($path)) {
                $content = shell_exec("tail -n {$lines} " . escapeshellarg($path));
                return $content ?: 'Log vacío.';
            }
            return "No se pudo leer el archivo: " . $e->getMessage();
        }
    }

    /**
     * Clear a log file
     */
    public function clearLog(string $path): bool
    {
        if (str_contains($path, '..')) {
            return false;
        }

        $available = $this->getAvailableLogs();
        $allowed = false;
        foreach ($available as $log) {
            if ($log['path'] === $path) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new \RuntimeException("Acceso denegado.");
        }

        try {
            $this->sudo->run(['truncate', '-s', '0', $path]);
            return true;
        } catch (\Throwable $e) {
            if (is_writable($path)) {
                return file_put_contents($path, '') !== false;
            }
            return false;
        }
    }
}
