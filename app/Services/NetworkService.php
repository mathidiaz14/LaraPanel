<?php

namespace App\Services;

use App\Shell\ShellExecutor;
use App\Shell\SudoExecutor;
use Throwable;

/**
 * NetworkService — Active network connections via `ss`.
 *
 * Uses sudo so process names are visible for sockets owned by other users.
 */
class NetworkService
{
    public function __construct(
        protected ShellExecutor $shell,
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Listening sockets (TCP/UDP) with owning process.
     *
     * @return array[] proto, state, local_addr, local_port, peer, process, pid, user
     */
    public function getListeningPorts(): array
    {
        return $this->query(['ss', '-tulnp']);
    }

    /**
     * Established connections with remote peers.
     *
     * @return array[]
     */
    public function getEstablished(): array
    {
        $rows = $this->query(['ss', '-tupn', 'state', 'established']);

        // Exclude loopback-to-loopback noise
        return array_values(array_filter($rows, fn($r) =>
            !str_starts_with($r['peer'], '127.0.0.1') && $r['peer'] !== '0.0.0.0:*'
        ));
    }

    /**
     * Combined overview for the page summary cards.
     */
    public function overview(): array
    {
        $listeners  = $this->getListeningPorts();
        $established = $this->getEstablished();

        $remoteIps = [];
        foreach ($established as $row) {
            $ip = explode(':', $row['peer'])[0] ?? '';
            if ($ip !== '' && $ip !== '*') {
                $remoteIps[$ip] = true;
            }
        }

        return [
            'listening'   => count($listeners),
            'established' => count($established),
            'unique_ips'  => count($remoteIps),
        ];
    }

    /**
     * Run an `ss` command and parse its output into rows.
     */
    protected function query(array $command): array
    {
        try {
            $result = $this->sudo->withTimeout(10)->run($command, checkExit: false);
        } catch (Throwable) {
            return [];
        }

        $rows = [];
        foreach ($result->lines() as $line) {
            $parsed = $this->parseLine($line);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        usort($rows, fn($a, $b) => [$a['proto'], $a['local_port']] <=> [$b['proto'], $b['local_port']]);

        // Collapse identical sockets (same process holding several fds)
        $unique = [];
        foreach ($rows as $r) {
            $key = md5(serialize([$r['proto'], $r['state'], $r['local_addr'], $r['local_port'], $r['peer'], $r['process'], $r['pid']]));
            $unique[$key] ??= $r;
        }

        return array_values($unique);
    }

    /**
     * Parse one line of `ss` output.
     *
     * With a state filter (`ss ... state established`) the State column is
     * omitted; without it, it is present. Handles both shapes:
     *   tcp  LISTEN 0 128 0.0.0.0:80 0.0.0.0:* users:(("nginx",pid=1000,fd=6))
     *   tcp  0 0 1.2.3.4:443 5.6.7.8:51000 users:(("nginx",pid=1000,fd=12))
     */
    protected function parseLine(string $line): ?array
    {
        $cols = preg_split('/\s+/', trim($line));
        if (count($cols) < 5 || $cols[0] === 'Netid') {
            return null;
        }

        $netid = array_shift($cols);
        if (!preg_match('/^(tcp|udp)6?$/', $netid)) {
            return null;
        }

        // State column only exists when the output is not state-filtered
        $state = ctype_digit($cols[0]) ? 'established' : strtolower(array_shift($cols));

        if (!ctype_digit($cols[0] ?? '') || !ctype_digit($cols[1] ?? '')) {
            return null;
        }
        $recvq = array_shift($cols);
        $sendq = array_shift($cols);

        $local = array_shift($cols) ?? '';
        $peer  = array_shift($cols) ?? '';
        $rest  = implode(' ', $cols);

        [$localAddr, $localPort] = $this->splitAddrPort($local);
        [$peerAddr]              = $this->splitAddrPort($peer);

        // Strip interface suffix: "178.x.x.x%eth0" -> "178.x.x.x"
        $localAddr = preg_replace('/%[a-z0-9._-]+$/i', '', $localAddr);

        $process = null;
        $pid     = null;
        if ($rest !== '' && preg_match('/users:\(\("([^"]+)",pid=(\d+)/', $rest, $m)) {
            $process = $m[1];
            $pid     = (int)$m[2];
        }

        // Resolve the socket owner user from /proc when possible (local only)
        $user = $pid !== null ? ($this->resolveUser($pid) ?? 'desconocido') : null;

        return [
            'proto'      => str_ends_with($netid, '6') ? substr($netid, 0, -1) . 'v6' : $netid,
            'state'      => $state === 'unconn' ? 'unconn' : ($state ?: 'established'),
            'recv_q'     => (int)$recvq,
            'send_q'     => (int)$sendq,
            'local_addr' => $localAddr,
            'local_port' => (int)$localPort,
            'peer'       => $peerAddr,
            'process'    => $process,
            'pid'        => $pid,
            'user'       => $user,
        ];
    }

    /**
     * Split "host:port" handling IPv6 brackets like [::]:22.
     *
     * @return string[] [addr, port]
     */
    protected function splitAddrPort(string $address): array
    {
        if (preg_match('/^\[(.+)\]:(\d+|\*)$/', $address, $m)) {
            return [$m[1], $m[2]];
        }
        $pos = strrpos($address, ':');
        if ($pos === false) return [$address, '*'];
        return [substr($address, 0, $pos), substr($address, $pos + 1)];
    }

    /**
     * Best-effort owner lookup for a PID via /proc (works without sudo for
     * our own processes; returns null otherwise).
     */
    protected function resolveUser(int $pid): ?string
    {
        $stat = @file_get_contents("/proc/{$pid}/status");
        if (!$stat || !preg_match('/^Uid:\s+\d+\s+(\d+)/m', $stat, $m)) {
            return null;
        }

        $uid = (int)$m[1];
        if (function_exists('posix_getpwuid')) {
            $info = posix_getpwuid($uid);
            if ($info) return $info['name'];
        }

        return (string)$uid;
    }
}
