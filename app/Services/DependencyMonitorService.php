<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;

/**
 * DependencyMonitorService — tracks OS package updates and surfaces
 * security-relevant upgrade counts plus best-effort CVE hints.
 *
 * All shell interaction goes through SudoExecutor (whitelisted binaries:
 * apt, apt-get, dpkg, debsecan). Destructive operations are dry-run by
 * default and only applied when the caller explicitly opts in.
 *
 * Security rules enforced here:
 *  - Never run `dist-upgrade` / `full-upgrade` / wiping operations.
 *  - Package names are strictly validated before being passed to apt-get.
 *  - A real upgrade is ONLY executed when $apply === true; otherwise the
 *    exact same command is simulated with `--simulate`.
 */
class DependencyMonitorService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * List available package updates (name, old version, new version).
     *
     * Preferred source: `apt list --upgradable`. Fallback: `apt-get -s upgrade`.
     * Result is capped at 200 entries.
     *
     * @return array{count:int, method:string, message:string, packages:array}
     */
    public function availableUpdates(): array
    {
        if (!app()->isProduction()) {
            return $this->getSimulatedUpdates();
        }

        try {
            $result = $this->sudo->run(['apt', 'list', '--upgradable'], checkExit: false);
            $rows = $this->parseAptList($result->stdout);
            $method = 'apt-list';

            if (empty($rows) && $result->successful() && str_contains($result->stdout, 'Listing')) {
                // Nothing upgradable, `apt list` still prints a header.
                $method = 'apt-list';
            } elseif (empty($rows)) {
                $sim = $this->sudo->run(['apt-get', '-s', 'upgrade'], checkExit: false);
                $rows = $this->parseAptGetSimulate($sim->stdout);
                $method = 'apt-get-simulate';
            }

            $packages = array_slice($rows, 0, 200);

            return [
                'count'    => count($packages),
                'method'   => $method,
                'message'  => 'ok',
                'packages' => $packages,
            ];
        } catch (\Throwable $e) {
            Log::warning('DependencyMonitorService::availableUpdates failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'count'    => 0,
                'method'   => 'none',
                'message'  => $e->getMessage(),
                'packages' => [],
            ];
        }
    }

    /**
     * Best-effort list of security-relevant package updates.
     *
     * Preferred source: `debsecan --suite <codename>` when the binary exists,
     * otherwise a heuristic derived from `apt` output (packages whose source
     * suite contains `-security` or whose target version has a `~` suffix).
     *
     * Never raises: on any failure returns an unsupported fallback payload.
     *
     * @return array{support:bool, source:string, message:string, count:int, packages:array}
     */
    public function securityUpdates(): array
    {
        if (!app()->isProduction()) {
            return $this->getSimulatedSecurityUpdates();
        }

        try {
            $probe = $this->sudo->run(['which', 'debsecan'], checkExit: false);

            if ($probe->successful()) {
                $suite = $this->distributionCodename();
                $res = $this->sudo
                    ->withTimeout(120)
                    ->run(['debsecan', '--suite', $suite], checkExit: false);

                $packages = $res->successful() ? $this->parseDebsecan($res->stdout) : [];
                $rowCount = 0;
                foreach ($res->lines() as $line) {
                    if (str_starts_with($line, 'CVE-')) {
                        $rowCount++;
                    }
                }

                // Only trust debsecan when it actually produced CVE rows;
                // otherwise fall back to the apt heuristic below.
                if (!empty($packages) || $rowCount > 0) {
                    return [
                        'support'  => true,
                        'source'   => 'debsecan',
                        'message'  => 'Reporte de seguridad generado con debsecan (suite: ' . $suite . ').',
                        'count'    => count($packages),
                        'packages' => $packages,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('DependencyMonitorService::securityUpdates (debsecan) failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: heuristic from `apt` output.
        try {
            $updates = $this->availableUpdates();
            $security = array_values(array_filter(
                $updates['packages'],
                fn (array $p) => ($p['security'] ?? false),
            ));

            return [
                'support'  => false,
                'source'   => 'apt-heuristic',
                'message'  => 'debsecan no está instalado. Lista derivada de `apt list --upgradable` (suites -security / versiones con sufijo ~).',
                'count'    => count($security),
                'packages' => array_map(fn (array $p) => [
                    'name'    => $p['name'] ?? '',
                    'version' => $p['new'] ?? '',
                    'source'  => $p['source'] ?? '',
                    'fixed'   => $p['new'] ?? '',
                    'urgency' => 'unknown',
                    'cves'    => [],
                ], $security),
            ];
        } catch (\Throwable $e) {
            return [
                'support'  => false,
                'source'   => 'none',
                'message'  => $e->getMessage(),
                'count'    => 0,
                'packages' => [],
            ];
        }
    }

    /**
     * Installed versions of core services (nginx, php-fpm, mysql, postfix,
     * dovecot, redis, docker) via `dpkg -s`.
     *
     * Dangerous/install-dependent probing is short-circuited outside
     * production and replaced with configured/sample data.
     *
     * @return array<int, array{key:string, package:string, version:?string}>
     */
    public function coreServiceVersions(): array
    {
        if (!app()->isProduction()) {
            return [
                ['key' => 'nginx',       'package' => 'nginx',        'version' => '1.24.1'],
                ['key' => 'php-fpm',     'package' => 'php8.3-fpm',   'version' => '8.3.14-1'],
                ['key' => 'mysql',       'package' => 'mysql-server', 'version' => '8.0.36-0ubuntu0.22.04'],
                ['key' => 'postfix',     'package' => 'postfix',      'version' => '3.6.4-1ubuntu2'],
                ['key' => 'dovecot',     'package' => 'dovecot-core', 'version' => '2.3.16-1ubuntu2'],
                ['key' => 'redis',       'package' => 'redis-server', 'version' => '7.0.15-1'],
                ['key' => 'docker',      'package' => 'docker-ce',    'version' => '25.0.3-1'],
            ];
        }

        $pkgs = [
            ['key' => 'nginx',       'package' => 'nginx'],
            ['key' => 'php-fpm',     'package' => 'php8.4-fpm'],
            ['key' => 'php-fpm',     'package' => 'php8.3-fpm'],
            ['key' => 'php-fpm',     'package' => 'php8.2-fpm'],
            ['key' => 'mysql',       'package' => 'mysql-server'],
            ['key' => 'postfix',     'package' => 'postfix'],
            ['key' => 'dovecot',     'package' => 'dovecot-core'],
            ['key' => 'redis',       'package' => 'redis-server'],
            ['key' => 'docker',      'package' => 'docker-ce'],
            ['key' => 'docker',      'package' => 'docker.io'],
        ];

        $found = [];
        foreach ($pkgs as $entry) {
            $already = collect($found)->contains(fn ($f) => $f['key'] === $entry['key']);
            if ($already) {
                continue;
            }

            $version = $this->dpkgVersion($entry['package']);

            if ($version !== null) {
                $entry['version'] = $version;
                $found[] = $entry;
            }
        }

        return array_values($found);
    }

    /**
     * Non-destructive upgrade flow.
     *
     * Always starts with `apt-get --simulate upgrade <pkgs>`. The real
     * upgrade ONLY runs when $apply === true and the dry-run succeeded.
     * Every invocation is recorded in the AuditLog under `system.apt.upgrade`.
     *
     * @param string[] $packageNames
     * @param bool     $apply        Whether to actually install after simulating.
     *
     * @return array{ok:bool, applied:bool, message:string, preview:array}
     */
    public function upgradePackages(array $packageNames, bool $apply = false): array
    {
        $clean = [];
        foreach ($packageNames as $name) {
            if (!is_string($name)) {
                continue;
            }
            $name = trim($name);
            if ($name === '' || $name === 'dist-upgrade' || $name === 'full-upgrade') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\.\+\-_]*$/', $name)) {
                continue;
            }
            $clean[] = $name;
        }
        $clean = array_values(array_unique($clean));

        if (in_array('dist-upgrade', $clean, true)) {
            return $this->denied('Acción denegada: no se admite dist-upgrade desde el panel.');
        }

        if (empty($clean)) {
            return $this->denied('No se seleccionaron paquetes válidos para actualizar.');
        }

        try {
            // Always dry-run first, even when applying.
            $dry = $this->sudo
                ->withTimeout(120)
                ->run(['apt-get', '--simulate', 'upgrade', ...$clean], checkExit: false);

            $preview = $dry->successful() ? $this->parseAptGetSimulate($dry->stdout) : [];

            AuditLog::record(
                action:  'system.apt.upgrade',
                subject: implode(', ', $clean),
                meta:    [
                    'mode'    => 'dry-run',
                    'ok'      => $dry->successful(),
                    'preview' => array_slice($preview, 0, 50),
                ],
                severity: 'info',
            );

            if (!$dry->successful()) {
                return [
                    'ok'       => false,
                    'applied'  => false,
                    'message'  => 'Simulación fallida: ' . trim($dry->stderr ?: $dry->stdout),
                    'preview'  => [],
                ];
            }

            if (!$apply) {
                return [
                    'ok'       => true,
                    'applied'  => false,
                    'message'  => 'Simulación completada correctamente. Ningún paquete fue modificado.',
                    'preview'  => $preview,
                ];
            }

            if (!app()->isProduction()) {
                return $this->denied('No se puede aplicar una actualización real fuera de producción.');
            }

            $result = $this->sudo
                ->withTimeout(1800)
                ->run(['apt-get', '-y', 'upgrade', ...$clean], checkExit: false);

            AuditLog::record(
                action:  'system.apt.upgrade',
                subject: implode(', ', $clean),
                meta:    [
                    'mode'    => 'apply',
                    'ok'      => $result->successful(),
                    'stderr'  => substr($result->stderr, 0, 1000),
                ],
                severity: 'warning',
            );

            return [
                'ok'       => $result->successful(),
                'applied'  => true,
                'message'  => $result->successful()
                    ? 'Paquetes actualizados correctamente.'
                    : 'Falló la actualización: ' . trim($result->stderr),
                'preview'  => $preview,
            ];
        } catch (\Throwable $e) {
            Log::error('DependencyMonitorService::upgradePackages failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->denied('Error al preparar la actualización: ' . $e->getMessage());
        }
    }

    // ─── Parsers ─────────────────────────────────────────────────────────────

    /**
     * Parse `apt list --upgradable` output.
     *
     * Line format:
     *   pkg/suite new-version arch [upgradable from: old-version]
     */
    protected function parseAptList(string $stdout): array
    {
        $rows = [];

        foreach (explode("\n", $stdout) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'Listing')) {
                continue;
            }

            if (preg_match('/^(\S+?)\/(\S+)\s+(\S+)\s+(\S+)\s*\[upgradable from:\s*(.+?)\]\s*$/', $line, $m)) {
                $source = $m[2];
                $new    = $m[3];
                $rows[] = [
                    'name'     => $m[1],
                    'old'      => trim($m[5]),
                    'new'      => $new,
                    'source'   => $source,
                    'security' => $this->looksSecurity($source, $new),
                ];
            }
        }

        return $rows;
    }

    /**
     * Parse `apt-get -s upgrade` output (`Inst` lines).
     *
     * Line format:
     *   Inst pkg [old-version] (new-version suite [arch])
     */
    protected function parseAptGetSimulate(string $stdout): array
    {
        $rows = [];

        foreach (explode("\n", $stdout) as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'Inst ')) {
                continue;
            }

            if (preg_match('/^Inst\s+(\S+)\s+(?:\[([^\]]*)\]\s+)?\((\S+)\s+([^)]+)\).*$/', $line, $m)) {
                $new    = $m[3];
                $source = trim($m[4]);
                $rows[] = [
                    'name'     => $m[1],
                    'old'      => isset($m[2]) ? trim($m[2]) : '',
                    'new'      => $new,
                    'source'   => $source,
                    'security' => $this->looksSecurity($source, $new),
                ];
            } else {
                // Minimal fallback: at least capture the package name.
                if (preg_match('/^Inst\s+(\S+)/', $line, $m)) {
                    $rows[] = [
                        'name'     => $m[1],
                        'old'      => '',
                        'new'      => '',
                        'source'   => '',
                        'security' => false,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Parse `debsecan --suite <codename>` default text output.
     *
     * debsecan prints one section per package, e.g.:
     *   Security vulnerabilities in pkg:
     *   CVE-2021-... <flavor> <fixed-version> ... <urgency>
     *
     * @return array<int, array{name:string, cves:array<int,string>, urgency:string, fixed:string}>
     */
    protected function parseDebsecan(string $stdout): array
    {
        $packages = [];
        $current  = null;

        foreach (explode("\n", $stdout) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^Security vulnerabilities? in\s+(.+?):?\s*$/i', $line, $m)) {
                $current = trim($m[1]);
                $packages[$current] = [
                    'name'    => $current,
                    'cves'    => [],
                    'urgency' => 'unknown',
                    'fixed'   => '',
                ];
                continue;
            }

            if ($current !== null) {
                if (preg_match('/^(CVE-\d{4}-\d+)[^\s]*\s+(\S+)\s+(\S+)?\s*(.*)$/i', $line, $m)) {
                    $cve = $m[1];
                    if (!in_array($cve, $packages[$current]['cves'], true)) {
                        $packages[$current]['cves'][] = $cve;
                    }
                    if (($m[2] ?? '') === 'fixed') {
                        $packages[$current]['fixed'] = $m[3] ?? '';
                    }
                    if (in_array($m[2] ?? '', ['unknown', 'unimportant', 'low', 'medium', 'high', 'critical'], true)) {
                        $packages[$current]['urgency'] = $m[2];
                    }
                }

                continue;
            }

            // Bare lines with no header yet (very defensive): store CVE directly.
            if (preg_match('/^CVE-\d{4}-\d+/i', $line)) {
                $packages[$line] = [
                    'name'    => $line,
                    'cves'    => [],
                    'urgency' => 'unknown',
                    'fixed'   => '',
                ];
            }
        }

        return array_values($packages);
    }

    /**
     * Heuristic: does this package update look security related?
     */
    protected function looksSecurity(string $source, string $version): bool
    {
        if (str_contains($source, '-security')) {
            return true;
        }
        // Tilde suffix marks security revision in several Debian derivatives
        // (e.g. 1.2.3~deb12u1, 2.4~8-1).
        return preg_match('/[0-9]~/', $version) === 1;
    }

    /**
     * Read the distro codename from /etc/os-release (used for debsecan).
     */
    protected function distributionCodename(): string
    {
        $osRelease = (string) @file_get_contents('/etc/os-release');
        if ($osRelease !== '' && preg_match('/^VERSION_CODENAME=(.+)$/m', trim($osRelease), $m)) {
            $codename = trim($m[1], "\"' \n\r\t\v\0");
            if ($codename !== '') {
                return $codename;
            }
        }
        return 'stable';
    }

    /**
     * Query the installed version of a package via `dpkg -s`.
     */
    protected function dpkgVersion(string $package): ?string
    {
        try {
            $res = $this->sudo->run(['dpkg', '-s', $package], checkExit: false);
            if (!$res->successful()) {
                return null;
            }
            if (preg_match('/^Version:\s*(.+)$/m', $res->stdout, $m)) {
                return trim($m[1]);
            }
        } catch (\Throwable $e) {
            Log::debug('dpkg -s failed', ['pkg' => $package, 'error' => $e->getMessage()]);
        }

        return null;
    }

    protected function denied(string $message): array
    {
        return [
            'ok'       => false,
            'applied'  => false,
            'message'  => $message,
            'preview'  => [],
        ];
    }

    // ─── Dev Simulation (deterministic per hour) ─────────────────────────────

    protected function getSimulatedUpdates(): array
    {
        $seed = (int) floor(time() / 3600);
        mt_srand($seed);

        $pool = [
            ['name' => 'curl',     'old' => '7.81.0-1ubuntu1.13', 'new' => '7.81.0-1ubuntu1.16', 'source' => 'jammy-updates'],
            ['name' => 'openssl',  'old' => '3.0.2-0ubuntu1.12',  'new' => '3.0.2-0ubuntu1.17',  'source' => 'jammy-security'],
            ['name' => 'nginx',    'old' => '1.22.1-1',           'new' => '1.24.0-1',           'source' => 'jammy-updates'],
            ['name' => 'bash',     'old' => '5.1-6ubuntu1',       'new' => '5.1-6ubuntu1.1',     'source' => 'jammy-updates'],
            ['name' => 'redis-server', 'old' => '7.0.8-1',        'new' => '7.0.15-1',           'source' => 'jammy-updates'],
            ['name' => 'vim',      'old' => '8.2.3995-1ubuntu2',  'new' => '8.2.3995-1ubuntu2.4', 'source' => 'jammy-updates'],
            ['name' => 'openssh-server', 'old' => '8.9p1-3build0ubuntu0.22.04', 'new' => '8.9p1-3ubuntu0.5', 'source' => 'jammy-security'],
            ['name' => 'libssl3',  'old' => '3.0.2-0ubuntu1.12',  'new' => '3.0.2-0ubuntu1.17',  'source' => 'jammy-security'],
            ['name' => 'ca-certificates', 'old' => '20211016ubuntu0.22.04.1', 'new' => '20230311ubuntu0.22.04.1', 'source' => 'jammy-updates'],
            ['name' => 'systemd',  'old' => '249.11-0ubuntu3.12', 'new' => '249.11-0ubuntu3.15', 'source' => 'jammy-updates'],
        ];

        shuffle($pool);
        mt_srand(); // restore non-deterministic RNG

        $packages = array_slice($pool, 0, 6);

        return [
            'count'    => count($packages),
            'method'   => 'simulated',
            'message'  => 'datos simulados (entorno de desarrollo)',
            'packages' => array_map(fn (array $p) => $p + [
                'security' => str_contains($p['source'], '-security'),
            ], $packages),
        ];
    }

    protected function getSimulatedSecurityUpdates(): array
    {
        $support = true;
        $packages = [
            ['name' => 'openssl',          'version' => '3.0.2-0ubuntu1.12', 'fixed' => '3.0.2-0ubuntu1.17', 'urgency' => 'high',    'cves' => ['CVE-2023-0464', 'CVE-2023-0465']],
            ['name' => 'openssh-server',   'version' => '8.9p1-3build0ubuntu0.22.04', 'fixed' => '8.9p1-3ubuntu0.5', 'urgency' => 'medium', 'cves' => ['CVE-2023-38408']],
            ['name' => 'libssl3',          'version' => '3.0.2-0ubuntu1.12', 'fixed' => '3.0.2-0ubuntu1.17', 'urgency' => 'high',    'cves' => ['CVE-2023-0464']],
        ];

        return [
            'support'  => $support,
            'source'   => 'debsecan',
            'message'  => 'Reporte de seguridad simulado (entorno de desarrollo).',
            'count'    => count($packages),
            'packages' => $packages,
        ];
    }
}