<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Throwable;

/**
 * ServiceManagerService — Start/stop/restart the panel-managed systemd services.
 *
 * Security: the service name is ALWAYS validated against this fixed internal
 * list. User input never reaches systemctl unvalidated.
 */
class ServiceManagerService
{
    /** Allowed systemctl actions. */
    public const ACTIONS = ['start', 'stop', 'restart'];

    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Build the full service list with live status.
     *
     * @return array[] name, label, icon, critical, description, active
     */
    public function listServices(): array
    {
        $services = $this->catalog();

        foreach ($services as &$svc) {
            $svc['active'] = $this->isActive($svc['name']);
        }

        return $services;
    }

    /**
     * Run an action (start|stop|restart) on a whitelisted service. Audited.
     */
    public function performAction(string $service, string $action): bool
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Acción inválida: [{$action}].");
        }

        $known = array_column($this->catalog(), 'name', 'name');
        if (!isset($known[$service])) {
            throw new \InvalidArgumentException("Servicio no permitido: [{$service}].");
        }

        $result = $this->sudo
            ->withTimeout(60)
            ->run(['systemctl', $action, $service], checkExit: false);

        try {
            AuditLog::record(
                action:   "service.{$action}",
                subject:  $service,
                meta:     [
                    'success' => $result->successful(),
                    'stderr'  => substr($result->stderr, 0, 300),
                ],
                severity: $action === 'start' ? 'info' : 'warning',
            );
        } catch (Throwable) {
            // Audit persistence is best-effort
        }

        if (!$result->successful()) {
            throw new \RuntimeException(
                "systemctl {$action} {$service} falló: " . substr($result->stderr, 0, 200)
            );
        }

        return true;
    }

    public function isActive(string $service): bool
    {
        try {
            $result = $this->sudo->withTimeout(5)->run(['systemctl', 'is-active', $service], checkExit: false);
            return trim($result->stdout) === 'active';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Merge the static catalog with PHP-FPM versions detected on disk.
     *
     * @return array[]
     */
    protected function catalog(): array
    {
        $services = [
            ['name' => 'nginx',        'label' => 'Nginx',           'icon' => 'fa-solid fa-server',        'critical' => true,  'description' => 'Servidor web y proxy inverso'],
            ['name' => 'mysql',        'label' => 'MySQL / MariaDB', 'icon' => 'fa-solid fa-database',      'critical' => true,  'description' => 'Base de datos principal'],
            ['name' => 'php8.3-fpm',   'label' => 'PHP-FPM 8.3',     'icon' => 'fa-solid fa-code',          'critical' => true,  'description' => 'Intérprete PHP para sitios'],
            ['name' => 'redis-server', 'label' => 'Redis',           'icon' => 'fa-solid fa-bolt',          'critical' => false, 'description' => 'Cache y colas'],
            ['name' => 'memcached',    'label' => 'Memcached',       'icon' => 'fa-solid fa-bolt',          'critical' => false, 'description' => 'Cache de objetos'],
            ['name' => 'docker',       'label' => 'Docker',          'icon' => 'fa-brands fa-docker',       'critical' => false, 'description' => 'Contenedores'],
            ['name' => 'fail2ban',     'label' => 'Fail2ban',        'icon' => 'fa-solid fa-shield-halved', 'critical' => false, 'description' => 'Protección fuerza bruta'],
            ['name' => 'postfix',      'label' => 'Postfix',         'icon' => 'fa-solid fa-envelope',      'critical' => false, 'description' => 'Servidor SMTP (correo saliente)'],
            ['name' => 'dovecot',      'label' => 'Dovecot',         'icon' => 'fa-solid fa-inbox',         'critical' => false, 'description' => 'Servidor IMAP/POP3'],
            ['name' => 'pdns',         'label' => 'PowerDNS',        'icon' => 'fa-solid fa-globe',         'critical' => false, 'description' => 'Servidor DNS autoritativo'],
            ['name' => 'cron',         'label' => 'Cron',            'icon' => 'fa-solid fa-clock',         'critical' => false, 'description' => 'Tareas programadas del sistema'],
            ['name' => 'ssh',          'label' => 'OpenSSH',         'icon' => 'fa-solid fa-terminal',      'critical' => true,  'description' => 'Acceso SSH al servidor'],
            ['name' => 'clamav-daemon','label' => 'ClamAV',          'icon' => 'fa-solid fa-virus-slash',   'critical' => false, 'description' => 'Antivirus'],
        ];

        // Detect additional installed PHP-FPM versions
        $known = array_column($services, null, 'name');
        foreach (glob('/etc/php/*/fpm') ?: [] as $dir) {
            if (!is_dir($dir)) continue;
            $version = basename(dirname($dir));
            $name    = "php{$version}-fpm";
            if (!isset($known[$name])) {
                $services[] = [
                    'name'        => $name,
                    'label'       => "PHP-FPM {$version}",
                    'icon'        => 'fa-solid fa-code',
                    'critical'    => true,
                    'description' => "Intérprete PHP {$version} para sitios",
                ];
            }
        }

        return $services;
    }
}
