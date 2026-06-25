<?php

namespace App\Services;

use App\Models\Fail2banEvent;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;

class Fail2banService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Check if fail2ban is running.
     */
    public function isRunning(): bool
    {
        if (!app()->isProduction()) return true;

        try {
            $result = $this->sudo->run(['systemctl', 'is-active', 'fail2ban'], checkExit: false);
            return $result->output() === 'active';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get global fail2ban status and list of all jails.
     */
    public function getStatus(): array
    {
        if (!app()->isProduction()) {
            return $this->getSimulatedStatus();
        }

        // Use systemctl as authoritative source for running state
        $errorMsg = '';
        try {
            $svcResult = $this->sudo->run(['systemctl', 'is-active', 'fail2ban'], checkExit: false);
            $isRunning = $svcResult->output() === 'active';
            if (!$isRunning) {
                $errorMsg = $svcResult->output() . ($svcResult->stderr ? ' | ' . $svcResult->stderr : '');
            }
        } catch (\Throwable $e) {
            $isRunning = false;
            $errorMsg = $e->getMessage();
        }

        // Separately query jails (only possible if daemon is actually running)
        $jails = [];
        $rawOutput = '';
        if ($isRunning) {
            try {
                $result = $this->sudo->run(['fail2ban-client', 'status'], checkExit: false);
                $rawOutput = $result->output();
                if (preg_match('/Jail list:\s+(.+)/i', $rawOutput, $m)) {
                    $jails = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
                }
            } catch (\Throwable $e) {
                $rawOutput = $e->getMessage();
            }
        } else {
            $rawOutput = $errorMsg ?: 'Servicio detenido o no instalado.';
        }

        return [
            'running'    => $isRunning,
            'jails'      => $jails,
            'raw_output' => $rawOutput,
        ];
    }

    /**
     * Get detailed status of a specific jail (banned IPs, total bans, etc.).
     */
    public function getJailStatus(string $jail): array
    {
        if (!app()->isProduction()) {
            return $this->getSimulatedJailStatus($jail);
        }

        try {
            $result = $this->sudo->run(['fail2ban-client', 'status', $jail], checkExit: false);
            $output = $result->output();

            return [
                'name'        => $jail,
                'total_failed'=> $this->parseJailStat($output, 'Total failed'),
                'total_banned'=> $this->parseJailStat($output, 'Total banned'),
                'currently_banned' => $this->parseJailStat($output, 'Currently banned'),
                'currently_failed' => $this->parseJailStat($output, 'Currently failed'),
                'banned_ips'  => $this->parseBannedIps($output),
                'raw_output'  => $output,
            ];
        } catch (\Throwable $e) {
            return ['name' => $jail, 'total_failed' => 0, 'total_banned' => 0, 'currently_banned' => 0, 'currently_failed' => 0, 'banned_ips' => [], 'raw_output' => $e->getMessage()];
        }
    }

    /**
     * Ban an IP manually in a specific jail.
     */
    public function banIp(User $user, string $jail, string $ip, string $reason = ''): void
    {
        $this->validateIp($ip);
        $this->validateJailName($jail);

        if (app()->isProduction()) {
            $this->sudo->run(['fail2ban-client', 'set', $jail, 'banip', $ip]);
        }

        Fail2banEvent::create([
            'user_id'      => $user->id,
            'jail'         => $jail,
            'ip_address'   => $ip,
            'action'       => 'ban',
            'reason'       => $reason ?: 'Baneado manualmente desde LaraPanel',
            'banned_at'    => now(),
            'initiated_by' => 'admin',
        ]);

        AuditLog::record('fail2ban.ban', $ip, ['jail' => $jail, 'reason' => $reason]);
    }

    /**
     * Unban an IP from a specific jail.
     */
    public function unbanIp(User $user, string $jail, string $ip): void
    {
        $this->validateIp($ip);
        $this->validateJailName($jail);

        if (app()->isProduction()) {
            $this->sudo->run(['fail2ban-client', 'set', $jail, 'unbanip', $ip]);
        }

        Fail2banEvent::create([
            'user_id'      => $user->id,
            'jail'         => $jail,
            'ip_address'   => $ip,
            'action'       => 'unban',
            'initiated_by' => 'admin',
        ]);

        AuditLog::record('fail2ban.unban', $ip, ['jail' => $jail]);
    }

    /**
     * Unban an IP from ALL jails.
     */
    public function unbanIpGlobal(User $user, string $ip): array
    {
        $this->validateIp($ip);

        $status  = $this->getStatus();
        $unbanned = [];

        foreach ($status['jails'] as $jail) {
            try {
                $this->unbanIp($user, $jail, $ip);
                $unbanned[] = $jail;
            } catch (\Throwable) {
                // Ignore jails where IP wasn't banned
            }
        }

        return $unbanned;
    }

    /**
     * Restart fail2ban service.
     */
    public function restart(): void
    {
        if (app()->isProduction()) {
            $this->sudo->run(['systemctl', 'restart', 'fail2ban']);
        }
        AuditLog::record('fail2ban.restarted', 'fail2ban');
    }

    /**
     * Reload fail2ban config.
     */
    public function reload(): void
    {
        if (app()->isProduction()) {
            $this->sudo->run(['fail2ban-client', 'reload']);
        }
        AuditLog::record('fail2ban.reloaded', 'fail2ban');
    }

    /**
     * Get the last N lines of the fail2ban log.
     */
    public function getLogTail(int $lines = 100): string
    {
        if (!app()->isProduction()) {
            return $this->getSimulatedLog();
        }

        try {
            $result = $this->sudo->run(['tail', '-n', (string)$lines, '/var/log/fail2ban.log'], checkExit: false);
            return $result->output() ?: 'No se pudo leer el log de fail2ban.';
        } catch (\Throwable $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Get aggregated ban statistics across all jails.
     */
    public function getGlobalStats(): array
    {
        if (!app()->isProduction()) {
            return [
                'total_bans_today'  => rand(5, 50),
                'total_active_bans' => rand(10, 120),
                'jails_active'      => 6,
                'most_attacked_jail'=> 'sshd',
                'top_banned_ips'    => [
                    ['ip' => '185.220.101.45', 'count' => 234, 'country' => 'RU'],
                    ['ip' => '103.21.244.0',   'count' => 189, 'country' => 'CN'],
                    ['ip' => '91.121.50.196',  'count' => 145, 'country' => 'FR'],
                    ['ip' => '45.155.205.225', 'count' => 98,  'country' => 'RU'],
                    ['ip' => '179.60.150.2',   'count' => 76,  'country' => 'BR'],
                ],
            ];
        }

        $status = $this->getStatus();
        $totalActive = 0;
        $mostAttacked = ['name' => 'none', 'count' => 0];

        foreach ($status['jails'] as $jail) {
            $jailStatus = $this->getJailStatus($jail);
            $totalActive += $jailStatus['currently_banned'];
            if ($jailStatus['total_banned'] > $mostAttacked['count']) {
                $mostAttacked = ['name' => $jail, 'count' => $jailStatus['total_banned']];
            }
        }

        return [
            'total_bans_today'   => Fail2banEvent::where('action', 'ban')->whereDate('created_at', today())->count(),
            'total_active_bans'  => $totalActive,
            'jails_active'       => count($status['jails']),
            'most_attacked_jail' => $mostAttacked['name'],
            'top_banned_ips'     => [],
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function parseJailStat(string $output, string $key): int
    {
        preg_match("/{$key}:\s+(\d+)/i", $output, $m);
        return isset($m[1]) ? (int)$m[1] : 0;
    }

    protected function parseBannedIps(string $output): array
    {
        if (preg_match('/Banned IP list:\s+(.*)/i', $output, $m)) {
            $ips = array_filter(array_map('trim', explode(' ', $m[1])));
            return array_values($ips);
        }
        return [];
    }

    protected function validateIp(string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("La dirección IP «{$ip}» no es válida.");
        }
    }

    protected function validateJailName(string $jail): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $jail)) {
            throw new \InvalidArgumentException("Nombre de jail inválido: «{$jail}».");
        }
    }

    // ─── Dev Simulation ──────────────────────────────────────────────────────

    protected function getSimulatedStatus(): array
    {
        return [
            'running' => true,
            'jails'   => ['sshd', 'nginx-http-auth', 'nginx-botsearch', 'postfix', 'dovecot', 'phpmyadmin-syslog'],
            'raw_output' => "Status\n|- Number of jail:      6\n`- Jail list:   sshd, nginx-http-auth, nginx-botsearch, postfix, dovecot, phpmyadmin-syslog",
        ];
    }

    protected function getSimulatedJailStatus(string $jail): array
    {
        $data = [
            'sshd'              => ['total_failed' => 4821, 'total_banned' => 312, 'currently_banned' => 28, 'currently_failed' => 6, 'banned_ips' => ['185.220.101.45', '103.21.244.0', '91.121.50.196', '45.155.205.225', '179.60.150.2', '89.248.167.131']],
            'nginx-http-auth'   => ['total_failed' => 1203, 'total_banned' => 87,  'currently_banned' => 5,  'currently_failed' => 2, 'banned_ips' => ['194.165.16.11', '45.83.65.100']],
            'nginx-botsearch'   => ['total_failed' => 892,  'total_banned' => 44,  'currently_banned' => 3,  'currently_failed' => 0, 'banned_ips' => ['167.94.138.62']],
            'postfix'           => ['total_failed' => 2341, 'total_banned' => 156, 'currently_banned' => 12, 'currently_failed' => 3, 'banned_ips' => ['212.103.50.220', '5.188.210.33']],
            'dovecot'           => ['total_failed' => 983,  'total_banned' => 67,  'currently_banned' => 8,  'currently_failed' => 1, 'banned_ips' => ['103.108.231.12']],
            'phpmyadmin-syslog' => ['total_failed' => 341,  'total_banned' => 29,  'currently_banned' => 2,  'currently_failed' => 0, 'banned_ips' => []],
        ];

        $d = $data[$jail] ?? ['total_failed' => 0, 'total_banned' => 0, 'currently_banned' => 0, 'currently_failed' => 0, 'banned_ips' => []];
        return array_merge($d, [
            'name'       => $jail,
            'raw_output' => "Status for the jail: {$jail}\n|- Filter\n|  |- Currently failed:\t{$d['currently_failed']}\n|  `- Total failed:\t{$d['total_failed']}\n`- Actions\n   |- Currently banned:\t{$d['currently_banned']}\n   `- Total banned:\t{$d['total_banned']}\n   `- Banned IP list:\t" . implode(' ', $d['banned_ips']),
        ]);
    }

    protected function getSimulatedLog(): string
    {
        $now   = now();
        $lines = [];
        $ips   = ['185.220.101.45', '103.21.244.0', '91.121.50.196', '45.155.205.225', '179.60.150.2'];
        $jails = ['sshd', 'nginx-http-auth', 'postfix', 'dovecot'];

        for ($i = 0; $i < 30; $i++) {
            $ts    = $now->subSeconds(rand(10, 7200))->format('Y-m-d H:i:s');
            $ip    = $ips[array_rand($ips)];
            $jail  = $jails[array_rand($jails)];
            $type  = rand(0, 3);

            if ($type === 0) {
                $lines[] = "{$ts},000 fail2ban.actions   [1] NOTICE  [{$jail}] Ban {$ip}";
            } elseif ($type === 1) {
                $lines[] = "{$ts},000 fail2ban.actions   [1] NOTICE  [{$jail}] Unban {$ip}";
            } elseif ($type === 2) {
                $lines[] = "{$ts},000 fail2ban.filter    [1] INFO    [{$jail}] Found {$ip} - " . now()->format('Y-m-d H:i:s');
            } else {
                $lines[] = "{$ts},000 fail2ban.server    [1] INFO    --";
            }
        }

        usort($lines, fn($a, $b) => strcmp($b, $a));
        return implode("\n", $lines);
    }
}
