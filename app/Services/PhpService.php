<?php

namespace App\Services;

use App\Shell\SudoExecutor;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class PhpService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Get list of php versions with their service status and domains count.
     */
    public function getPhpVersionsStatus(): array
    {
        $configuredVersions = config('larapanel.server.php_versions', ['8.1', '8.2', '8.3']);
        $result = [];

        foreach ($configuredVersions as $version) {
            $serviceName = "php{$version}-fpm";
            
            // Check status via systemctl in prod, simulate in dev
            if (app()->isProduction()) {
                $status = $this->sudo->serviceStatus($serviceName);
                $isActive = $status['active'];
            } else {
                $isActive = ($version === '8.3'); // Simulating 8.3 active, others inactive/installed
            }

            // Count domains using this php version
            $domainCount = \App\Models\Domain::where('php_version', $version)->count();

            $result[] = [
                'version' => $version,
                'service' => $serviceName,
                'active' => $isActive,
                'domains_count' => $domainCount,
            ];
        }

        return $result;
    }

    /**
     * Restart a PHP-FPM service.
     */
    public function restartVersion(string $version): bool
    {
        $serviceName = "php{$version}-fpm";
        AuditLog::record('php.restart', $serviceName);

        if (!app()->isProduction()) {
            return true;
        }

        try {
            $this->sudo->restartService($serviceName);
            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to restart PHP service {$serviceName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Read custom settings from 99-larapanel.ini for a PHP version.
     */
    public function getSettings(string $version): array
    {
        $defaults = [
            'memory_limit' => '128M',
            'upload_max_filesize' => '2M',
            'post_max_size' => '8M',
            'max_execution_time' => '30',
            'max_input_time' => '60',
            'display_errors' => 'Off',
            'short_open_tag' => 'Off',
        ];

        if (!app()->isProduction()) {
            // Simulated settings storage in session or cache to test edits in dev
            return cache()->get("simulated_php_settings_{$version}", $defaults);
        }

        $iniPath = "/etc/php/{$version}/fpm/conf.d/99-larapanel.ini";
        
        // Read file if exists
        $settings = $defaults;
        if (file_exists($iniPath)) {
            $content = file_get_contents($iniPath);
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    // remove surrounding quotes
                    $value = trim($value, '"\'');
                    if (array_key_exists($key, $defaults)) {
                        $settings[$key] = $value;
                    }
                }
            }
        }

        return $settings;
    }

    /**
     * Write settings to 99-larapanel.ini and restart php-fpm.
     */
    public function updateSettings(string $version, array $settings): bool
    {
        AuditLog::record('php.settings.update', "PHP {$version}", $settings);

        if (!app()->isProduction()) {
            cache()->put("simulated_php_settings_{$version}", $settings, 3600);
            return true;
        }

        $iniPath = "/etc/php/{$version}/fpm/conf.d/99-larapanel.ini";
        $content = "; LaraPanel Custom PHP Settings\n";
        $content .= "; Generated automatically — do not modify directly\n\n";

        foreach ($settings as $key => $value) {
            // Basic sanitization/validation
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            $value = preg_replace('/[^a-zA-Z0-9_\-M]/', '', $value); // allow M for memory values
            $content .= "{$key} = \"{$value}\"\n";
        }

        try {
            $tmpFile = tempnam('/tmp', 'lp_php_ini_');
            file_put_contents($tmpFile, $content);

            // Copy to /etc/php/... via sudo
            $this->sudo->run(['cp', $tmpFile, $iniPath]);
            $this->sudo->run(['chmod', '644', $iniPath]);
            unlink($tmpFile);

            // Restart PHP-FPM service to apply
            $this->restartVersion($version);
            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to write PHP settings for {$version}: " . $e->getMessage());
            return false;
        }
    }
}
