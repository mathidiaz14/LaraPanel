<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MonitoringService
{
    /**
     * Get a full snapshot of current system metrics.
     */
    public function getSnapshot(): array
    {
        if (\App\Shell\ServerContext::isRemote()) {
            return $this->getRemoteSnapshot(\App\Shell\ServerContext::executor());
        }

        if (!app()->isProduction()) {
            return $this->getSimulatedSnapshot();
        }

        return [
            'cpu'    => $this->getCpuMetrics(),
            'ram'    => $this->getRamMetrics(),
            'disk'   => $this->getDiskMetrics(),
            'net'    => $this->getNetMetrics(),
            'load'   => $this->getLoadAverage(),
            'uptime' => $this->getUptime(),
            'procs'  => $this->getTopProcesses(),
            'ts'     => now()->timestamp,
        ];
    }

    /**
     * Legacy snapshot for main dashboard backwards compatibility.
     */
    public function snapshot(): array
    {
        $snap = $this->getSnapshot();
        // Always find the root (/) partition for consistent disk reporting
        $rootDisk = collect($snap['disk'])->firstWhere('mount', '/') ?? ($snap['disk'][0] ?? null);
        return [
            'cpu'       => $snap['cpu']['usage'],
            'ram'       => ['usage' => $snap['ram']['percent'], 'total' => $snap['ram']['total'], 'used' => $snap['ram']['used'], 'available' => $snap['ram']['free']],
            'disk'      => $rootDisk ? ['usage' => $rootDisk['percent'], 'total' => $rootDisk['size'], 'used' => $rootDisk['used'], 'free' => $rootDisk['free']] : ['usage'=>0,'total'=>0,'used'=>0,'free'=>0],
            'load'      => $snap['load'],
            'network'   => !empty($snap['net']) ? ['in' => $snap['net'][0]['rx_speed'], 'out' => $snap['net'][0]['tx_speed']] : ['in'=>0,'out'=>0],
            'uptime'    => $snap['uptime'],
            'system'    => $this->getSystemInfo(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Services status for main dashboard.
     */
    public function servicesStatus(): array
    {
        if (\App\Shell\ServerContext::isRemote()) {
            return $this->getRemoteServicesStatus(\App\Shell\ServerContext::executor());
        }

        if (!app()->isProduction()) {
            return [
                'nginx'      => true,
                'mysql'      => true,
                'php8.3-fpm' => true,
                'fail2ban'   => true,
                'postfix'    => true,
                'dovecot'    => true,
                'pdns'       => true,
            ];
        }

        $services = ['nginx', 'mysql', 'php8.3-fpm', 'fail2ban', 'postfix', 'dovecot', 'pdns'];
        $statuses = [];
        foreach ($services as $svc) {
            $statuses[$svc] = trim(shell_exec("systemctl is-active {$svc} 2>/dev/null") ?? '') === 'active';
        }
        return $statuses;
    }

    // ─── CPU ─────────────────────────────────────────────────────────────────

    public function getCpuMetrics(): array
    {
        // Read /proc/stat twice to calculate delta
        $stat1 = $this->readCpuStat();
        usleep(200000); // 200ms sample
        $stat2 = $this->readCpuStat();

        $delta = [];
        foreach ($stat2 as $key => $val) {
            $delta[$key] = $val - ($stat1[$key] ?? 0);
        }

        $total  = array_sum($delta);
        $idle   = $delta['idle'] + ($delta['iowait'] ?? 0);
        $usage  = $total > 0 ? round((($total - $idle) / $total) * 100, 1) : 0;

        return [
            'usage'   => $usage,
            'user'    => $total > 0 ? round(($delta['user'] / $total) * 100, 1) : 0,
            'system'  => $total > 0 ? round(($delta['system'] / $total) * 100, 1) : 0,
            'iowait'  => $total > 0 ? round((($delta['iowait'] ?? 0) / $total) * 100, 1) : 0,
            'cores'   => (int)(shell_exec('nproc') ?? 1),
            'model'   => trim(shell_exec("grep 'model name' /proc/cpuinfo | head -1 | cut -d: -f2") ?? 'Unknown CPU'),
        ];
    }

    protected function readCpuStat(): array
    {
        $line = explode(' ', trim(shell_exec("head -1 /proc/stat") ?? ''));
        return [
            'user'    => (int)($line[1] ?? 0),
            'nice'    => (int)($line[2] ?? 0),
            'system'  => (int)($line[3] ?? 0),
            'idle'    => (int)($line[4] ?? 0),
            'iowait'  => (int)($line[5] ?? 0),
            'irq'     => (int)($line[6] ?? 0),
            'softirq' => (int)($line[7] ?? 0),
        ];
    }

    // ─── RAM ─────────────────────────────────────────────────────────────────

    public function getRamMetrics(): array
    {
        $raw = shell_exec('cat /proc/meminfo') ?? '';
        $mem = [];
        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $mem[$m[1]] = (int)$m[2] * 1024; // convert kB -> bytes
            }
        }

        $total    = $mem['MemTotal']    ?? 0;
        $free     = $mem['MemFree']     ?? 0;
        $buffers  = $mem['Buffers']     ?? 0;
        $cached   = $mem['Cached']      ?? 0;
        $available= $mem['MemAvailable']?? ($free + $buffers + $cached);
        $used     = $total - $available;

        $swapTotal = $mem['SwapTotal'] ?? 0;
        $swapFree  = $mem['SwapFree']  ?? 0;
        $swapUsed  = $swapTotal - $swapFree;

        return [
            'total'      => $total,
            'used'       => $used,
            'free'       => $available,
            'buffers'    => $buffers,
            'cached'     => $cached,
            'percent'    => $total > 0 ? round(($used / $total) * 100, 1) : 0,
            'swap_total' => $swapTotal,
            'swap_used'  => $swapUsed,
            'swap_pct'   => $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100, 1) : 0,
        ];
    }

    // ─── Disk ─────────────────────────────────────────────────────────────────

    public function getDiskMetrics(): array
    {
        $raw = shell_exec("df -B1 --output=source,size,used,avail,pcent,target 2>/dev/null | grep -v tmpfs | grep -v udev | grep -v overlay | grep -v shm") ?? '';
        $partitions = [];

        foreach (array_slice(explode("\n", trim($raw)), 1) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 6) continue;
            [$dev, $size, $used, $avail, $pct, $mount] = $parts;
            if ((int)$size === 0) continue; // skip zero-size partitions
            $partitions[] = [
                'device'  => $dev,
                'size'    => (int)$size,
                'used'    => (int)$used,
                'free'    => (int)$avail,
                'percent' => (int)rtrim($pct, '%'),
                'mount'   => $mount,
            ];
        }

        // Sort so root (/) is always first
        usort($partitions, fn($a, $b) => ($a['mount'] === '/') ? -1 : 1);

        return $partitions;
    }

    // ─── System Info ─────────────────────────────────────────────────────────

    public function getSystemInfo(): array
    {
        if (!app()->isProduction()) {
            return [
                'kernel'   => '6.1.0-21-amd64',
                'os'       => 'Ubuntu 24.04.2 LTS',
                'hostname' => 'larapanel-dev',
                'uptime'   => '12d 4h 33m',
            ];
        }

        $kernel   = trim(shell_exec('uname -r') ?? 'Unknown');
        $hostname = trim(shell_exec('hostname') ?? 'Unknown');
        $uptime   = $this->getUptime();

        // Read /etc/os-release for distro name
        $os = 'Linux';
        if (file_exists('/etc/os-release')) {
            $lines = file('/etc/os-release', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $vars  = [];
            foreach ($lines as $line) {
                if (str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    $vars[$k] = trim($v, '"');
                }
            }
            $os = $vars['PRETTY_NAME'] ?? ($vars['NAME'] ?? 'Linux');
        }

        return compact('kernel', 'os', 'hostname', 'uptime');
    }

    // ─── Network ─────────────────────────────────────────────────────────────

    public function getNetMetrics(): array
    {
        $raw = shell_exec("cat /proc/net/dev") ?? '';
        $ifaces = [];

        foreach (array_slice(explode("\n", $raw), 2) as $line) {
            if (!str_contains($line, ':')) continue;
            [$iface, $data] = explode(':', $line, 2);
            $iface = trim($iface);
            if (in_array($iface, ['lo'])) continue;

            $cols = preg_split('/\s+/', trim($data));
            $ifaces[$iface] = [
                'rx_bytes' => (int)($cols[0] ?? 0),
                'tx_bytes' => (int)($cols[8] ?? 0),
            ];
        }

        // Calculate speed using cached previous snapshot
        $prev     = Cache::get('net_snapshot_prev', $ifaces);
        $interval = Cache::get('net_snapshot_ts', now()->timestamp);
        $elapsed  = max(1, now()->timestamp - $interval);

        $result = [];
        foreach ($ifaces as $name => $vals) {
            $prevRx = $prev[$name]['rx_bytes'] ?? $vals['rx_bytes'];
            $prevTx = $prev[$name]['tx_bytes'] ?? $vals['tx_bytes'];

            $result[] = [
                'interface' => $name,
                'rx_bytes'  => $vals['rx_bytes'],
                'tx_bytes'  => $vals['tx_bytes'],
                'rx_speed'  => max(0, (int)(($vals['rx_bytes'] - $prevRx) / $elapsed)),
                'tx_speed'  => max(0, (int)(($vals['tx_bytes'] - $prevTx) / $elapsed)),
            ];
        }

        Cache::put('net_snapshot_prev', $ifaces, 60);
        Cache::put('net_snapshot_ts', now()->timestamp, 60);

        return $result;
    }

    // ─── Load & Uptime ───────────────────────────────────────────────────────

    public function getLoadAverage(): array
    {
        $raw = shell_exec('cat /proc/loadavg') ?? '';
        $parts = explode(' ', $raw);
        return [
            '1m'  => (float)($parts[0] ?? 0),
            '5m'  => (float)($parts[1] ?? 0),
            '15m' => (float)($parts[2] ?? 0),
        ];
    }

    public function getUptime(): string
    {
        $raw = (float)(shell_exec('cat /proc/uptime') ?? 0);
        $seconds = (int)$raw;
        $days    = intdiv($seconds, 86400);
        $hours   = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) return "{$days}d {$hours}h {$minutes}m";
        if ($hours > 0) return "{$hours}h {$minutes}m";
        return "{$minutes}m";
    }

    // ─── Top Processes ───────────────────────────────────────────────────────

    public function getTopProcesses(int $limit = 8): array
    {
        $raw = shell_exec("ps aux --sort=-%cpu | head -" . ($limit + 1)) ?? '';
        $lines = array_slice(explode("\n", trim($raw)), 1);
        $procs = [];

        foreach ($lines as $line) {
            $cols = preg_split('/\s+/', trim($line), 11);
            if (count($cols) < 11) continue;
            $procs[] = [
                'user'    => $cols[0],
                'pid'     => (int)$cols[1],
                'cpu'     => (float)$cols[2],
                'mem'     => (float)$cols[3],
                'command' => basename(explode(' ', $cols[10])[0]),
                'full_cmd'=> $cols[10],
            ];
        }

        return $procs;
    }

    // ─── Dev Simulation ──────────────────────────────────────────────────────

    protected function getSimulatedSnapshot(): array
    {
        static $base_cpu = null;
        if ($base_cpu === null) $base_cpu = rand(15, 45);
        $cpu = min(100, max(2, $base_cpu + rand(-8, 8)));

        $ramTotal = 8 * 1024 * 1024 * 1024;
        $ramUsed  = (int)($ramTotal * (rand(45, 70) / 100));

        return [
            'cpu' => [
                'usage'  => $cpu,
                'user'   => round($cpu * 0.6, 1),
                'system' => round($cpu * 0.3, 1),
                'iowait' => round($cpu * 0.1, 1),
                'cores'  => 4,
                'model'  => 'Intel(R) Xeon(R) E5-2676 v3 @ 2.40GHz',
            ],
            'ram' => [
                'total'      => $ramTotal,
                'used'       => $ramUsed,
                'free'       => $ramTotal - $ramUsed,
                'buffers'    => 128 * 1024 * 1024,
                'cached'     => 512 * 1024 * 1024,
                'percent'    => round(($ramUsed / $ramTotal) * 100, 1),
                'swap_total' => 2 * 1024 * 1024 * 1024,
                'swap_used'  => rand(0, 200) * 1024 * 1024,
                'swap_pct'   => rand(0, 10),
            ],
            'disk' => [
                ['device' => '/dev/sda1', 'size' => 50*1024*1024*1024, 'used' => 18*1024*1024*1024, 'free' => 32*1024*1024*1024, 'percent' => 36, 'mount' => '/'],
                ['device' => '/dev/sda2', 'size' => 100*1024*1024*1024, 'used' => 55*1024*1024*1024, 'free' => 45*1024*1024*1024, 'percent' => 55, 'mount' => '/var'],
            ],
            'net' => [
                ['interface' => 'eth0', 'rx_bytes' => rand(1,5)*1024*1024*1024, 'tx_bytes' => rand(1,3)*1024*1024*1024, 'rx_speed' => rand(100,2000)*1024, 'tx_speed' => rand(50,800)*1024],
            ],
            'load' => ['1m' => round($cpu/100*4, 2), '5m' => round($cpu/100*3.5, 2), '15m' => round($cpu/100*3.2, 2)],
            'uptime' => '12d 4h 33m',
            'procs' => [
                ['user' => 'www-data', 'pid' => 1234, 'cpu' => round($cpu*0.4,1), 'mem' => 2.3, 'command' => 'php-fpm8.3', 'full_cmd' => 'php-fpm: pool www'],
                ['user' => 'mysql',    'pid' => 890,  'cpu' => round($cpu*0.2,1), 'mem' => 8.1, 'command' => 'mysqld',     'full_cmd' => '/usr/sbin/mysqld'],
                ['user' => 'www-data', 'pid' => 2341, 'cpu' => round($cpu*0.15,1),'mem' => 1.2, 'command' => 'nginx',      'full_cmd' => 'nginx: worker process'],
                ['user' => 'postfix',  'pid' => 3421, 'cpu' => 0.1, 'mem' => 0.4, 'command' => 'qmgr',       'full_cmd' => 'qmgr -l -t unix -u'],
                ['user' => 'root',     'pid' => 1,    'cpu' => 0.0, 'mem' => 0.1, 'command' => 'systemd',    'full_cmd' => '/sbin/init'],
            ],
            'ts' => now()->timestamp,
        ];
    }

    // ─── Formatters ──────────────────────────────────────────────────────────

    public static function formatBytes(int $bytes, int $precision = 1): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp   = min(floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / pow(1024, $exp), $precision) . ' ' . $units[$exp];
    }

    public static function formatSpeed(int $bytesPerSec): string
    {
        return self::formatBytes($bytesPerSec) . '/s';
    }

    /**
     * Fetch resource snapshot metrics from a remote server using SSH executor.
     */
    protected function getRemoteSnapshot(\App\Shell\RemoteShellExecutor $executor): array
    {
        // Default structure in case of SSH errors
        $snap = [
            'cpu' => ['usage' => 0, 'user' => 0, 'system' => 0, 'iowait' => 0, 'cores' => 1, 'model' => 'Unknown CPU (remoto)'],
            'ram' => ['total' => 0, 'used' => 0, 'free' => 0, 'buffers' => 0, 'cached' => 0, 'percent' => 0, 'swap_total' => 0, 'swap_used' => 0, 'swap_pct' => 0],
            'disk' => [['device' => '/dev/root', 'size' => 0, 'used' => 0, 'free' => 0, 'percent' => 0, 'mount' => '/']],
            'net' => [['interface' => 'eth0', 'rx_bytes' => 0, 'tx_bytes' => 0, 'rx_speed' => 0, 'tx_speed' => 0]],
            'load' => ['1m' => 0, '5m' => 0, '15m' => 0],
            'uptime' => 'Desconectado',
            'procs' => [],
            'ts' => now()->timestamp,
        ];

        try {
            // Get active remote stats from the cached stats or call service to fetch live
            $server = $executor->getServer();
            
            // If the server cache is older than 10 seconds, let's trigger a light ping
            if (!$server->last_ping_at || $server->last_ping_at->diffInSeconds(now()) > 10) {
                app(\App\Services\ServerService::class)->ping($server);
                $server->refresh();
            }

            $os = $server->os_info;
            if ($os) {
                $ram = $os['ram'] ?? [];
                $disk = $os['disk'] ?? [];

                $snap['cpu']['usage'] = $os['cpuPercent'] ?? 0;
                $snap['cpu']['cores'] = 1;
                $snap['cpu']['model'] = $os['kernel'] ?? 'Linux remoto';

                $snap['ram']['total'] = (int)(($ram['total'] ?? 0) * 1024 * 1024 * 1024);
                $snap['ram']['used'] = (int)(($ram['used'] ?? 0) * 1024 * 1024 * 1024);
                $snap['ram']['free'] = $snap['ram']['total'] - $snap['ram']['used'];
                $snap['ram']['percent'] = $ram['percent'] ?? 0;

                $snap['disk'] = [[
                    'device' => '/dev/sda1',
                    'size' => (int)(($disk['total'] ?? 0) * 1024 * 1024 * 1024),
                    'used' => (int)(($disk['used'] ?? 0) * 1024 * 1024 * 1024),
                    'free' => (int)((($disk['total'] ?? 0) - ($disk['used'] ?? 0)) * 1024 * 1024 * 1024),
                    'percent' => $disk['percent'] ?? 0,
                    'mount' => '/',
                ]];

                $snap['uptime'] = $os['uptime'] ?? 'N/A';
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MonitoringService remote snapshot error', ['error' => $e->getMessage()]);
        }

        return $snap;
    }

    /**
     * Fetch active system service states on remote server.
     */
    protected function getRemoteServicesStatus(\App\Shell\RemoteShellExecutor $executor): array
    {
        $services = ['nginx', 'mysql', 'php8.3-fpm', 'fail2ban', 'postfix', 'dovecot', 'pdns'];
        $statuses = [];

        foreach ($services as $svc) {
            try {
                // Check status via systemctl
                $res = $executor->withTimeout(2)->run(['systemctl', 'is-active', $svc], false);
                $statuses[$svc] = trim($res->stdout) === 'active';
            } catch (\Throwable) {
                $statuses[$svc] = false;
            }
        }

        return $statuses;
    }
}
