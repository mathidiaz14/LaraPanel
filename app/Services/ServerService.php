<?php

namespace App\Services;

use App\Models\Server;
use App\Shell\RemoteShellExecutor;
use phpseclib3\Crypt\RSA;
use Illuminate\Support\Facades\Log;

class ServerService
{
    /**
     * Generate a new 4096-bit RSA key pair.
     *
     * @return array ['private' => string, 'public' => string]
     */
    public function generateKeyPair(): array
    {
        $private = RSA::createKey(4096);
        $public = $private->getPublicKey();

        return [
            'private' => (string) $private,
            'public'  => (string) $public,
        ];
    }

    /**
     * Ping a remote server, test connection and refresh cached OS/system stats.
     */
    public function ping(Server $server): bool
    {
        if ($server->is_local) {
            $server->update([
                'status' => 'online',
                'last_ping_at' => now(),
                'latency_ms' => 0,
                'os_info' => $this->getLocalStats(),
            ]);
            return true;
        }

        $executor = new RemoteShellExecutor($server);
        $test = $executor->testConnection();

        if ($test['ok']) {
            $osInfo = $this->getRemoteStats($executor);
            $server->update([
                'status' => 'online',
                'last_ping_at' => now(),
                'latency_ms' => $test['latency_ms'],
                'os_info' => $osInfo,
            ]);
            return true;
        }

        $server->update([
            'status' => 'offline',
            'last_ping_at' => now(),
            'latency_ms' => null,
        ]);
        return false;
    }

    /**
     * Get system stats locally.
     */
    protected function getLocalStats(): array
    {
        // OS
        $os = PHP_OS_FAMILY;
        $kernel = php_uname('r');

        // Uptime
        $uptime = 'N/A';
        if (file_exists('/proc/uptime')) {
            $uptimeSecs = (float) explode(' ', file_get_contents('/proc/uptime'))[0];
            $uptime = $this->formatUptime($uptimeSecs);
        }

        // RAM (from /proc/meminfo)
        $ram = ['total' => 0, 'used' => 0, 'percent' => 0];
        if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $mTotal) && preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $mAvail)) {
                $totalKb = (int) $mTotal[1];
                $availKb = (int) $mAvail[1];
                $usedKb = $totalKb - $availKb;
                $ram['total'] = round($totalKb / 1024 / 1024, 1); // GB
                $ram['used'] = round($usedKb / 1024 / 1024, 1);
                $ram['percent'] = $totalKb > 0 ? round(($usedKb / $totalKb) * 100) : 0;
            }
        }

        // CPU Usage (from /proc/stat load average)
        $cpuPercent = 0;
        if (file_exists('/proc/loadavg')) {
            $load = explode(' ', file_get_contents('/proc/loadavg'))[0];
            $cores = 1;
            if (file_exists('/proc/cpuinfo')) {
                $cores = substr_count(file_get_contents('/proc/cpuinfo'), 'processor');
                if ($cores === 0) $cores = 1;
            }
            $cpuPercent = min(100, round(((float) $load / $cores) * 100));
        }

        // Disk Usage
        $disk = ['total' => 0, 'used' => 0, 'percent' => 0];
        $totalDisk = @disk_total_space('/');
        $freeDisk = @disk_free_space('/');
        if ($totalDisk > 0) {
            $usedDisk = $totalDisk - $freeDisk;
            $disk['total'] = round($totalDisk / 1024 / 1024 / 1024, 1); // GB
            $disk['used'] = round($usedDisk / 1024 / 1024 / 1024, 1);
            $disk['percent'] = round(($usedDisk / $totalDisk) * 100);
        }

        return compact('os', 'kernel', 'uptime', 'ram', 'cpuPercent', 'disk');
    }

    /**
     * Get system stats from a remote server via SSH commands.
     */
    protected function getRemoteStats(RemoteShellExecutor $executor): array
    {
        $stats = [
            'os' => 'Linux (remoto)',
            'kernel' => 'Desconocido',
            'uptime' => 'N/A',
            'ram' => ['total' => 0, 'used' => 0, 'percent' => 0],
            'cpuPercent' => 0,
            'disk' => ['total' => 0, 'used' => 0, 'percent' => 0],
        ];

        try {
            // OS / Kernel
            $res = $executor->run(['uname', '-sr'], false);
            if ($res->successful()) {
                $stats['kernel'] = trim($res->stdout);
            }

            // Uptime (seconds)
            $res = $executor->run(['cat', '/proc/uptime'], false);
            if ($res->successful()) {
                $uptimeSecs = (float) explode(' ', $res->stdout)[0];
                $stats['uptime'] = $this->formatUptime($uptimeSecs);
            }

            // RAM (free -m)
            $res = $executor->run(['free', '-m'], false);
            if ($res->successful()) {
                // Parse free -m:
                //               total        used        free      shared  buff/cache   available
                // Mem:           7960        1234        3456         120        3270        6321
                $lines = explode("\n", $res->stdout);
                foreach ($lines as $line) {
                    if (str_contains($line, 'Mem:')) {
                        $parts = array_values(array_filter(explode(' ', $line)));
                        $totalMb = (int) $parts[1];
                        $usedMb = (int) $parts[2];
                        $availableMb = isset($parts[6]) ? (int) $parts[6] : ($totalMb - $usedMb);
                        $realUsedMb = $totalMb - $availableMb;
                        $stats['ram']['total'] = round($totalMb / 1024, 1);
                        $stats['ram']['used'] = round($realUsedMb / 1024, 1);
                        $stats['ram']['percent'] = $totalMb > 0 ? round(($realUsedMb / $totalMb) * 100) : 0;
                        break;
                    }
                }
            }

            // CPU (loadavg / nproc)
            $cores = 1;
            $resCores = $executor->run(['nproc'], false);
            if ($resCores->successful()) {
                $cores = max(1, (int) trim($resCores->stdout));
            }
            $resLoad = $executor->run(['cat', '/proc/loadavg'], false);
            if ($resLoad->successful()) {
                $load = (float) explode(' ', $resLoad->stdout)[0];
                $stats['cpuPercent'] = min(100, round(($load / $cores) * 100));
            }

            // Disk Usage (df -h /)
            $resDisk = $executor->run(['df', '-B1', '/'], false);
            if ($resDisk->successful()) {
                $lines = explode("\n", trim($resDisk->stdout));
                if (isset($lines[1])) {
                    $parts = array_values(array_filter(explode(' ', $lines[1])));
                    if (count($parts) >= 5) {
                        $totalBytes = (float) $parts[1];
                        $usedBytes = (float) $parts[2];
                        $stats['disk']['total'] = round($totalBytes / 1024 / 1024 / 1024, 1);
                        $stats['disk']['used'] = round($usedBytes / 1024 / 1024 / 1024, 1);
                        $stats['disk']['percent'] = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100) : 0;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('ServerService: Failed to retrieve remote stats', ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    protected function formatUptime(float $seconds): string
    {
        $d = floor($seconds / 86400);
        $h = floor(($seconds % 86400) / 3600);
        $m = floor(($seconds % 3600) / 60);

        if ($d > 0) {
            return "{$d}d {$h}h {$m}m";
        }
        if ($h > 0) {
            return "{$h}h {$m}m";
        }
        return "{$m}m";
    }
}
