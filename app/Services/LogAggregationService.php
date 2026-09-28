<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Shell\SudoExecutor;

/**
 * LogAggregationService — unified, filterable view across system logs
 * (nginx access/error, php-fpm, mysql error log, mail.log, Laravel log and
 * the panel AuditLog table).
 *
 * All file sources are derived from config (`larapanel.paths.logs`) and glob
 * patterns evaluated server-side. Raw user paths are never followed: each path
 * is resolved through normalizeAndCheck() and must stay inside the allowed log
 * roots. In non-production, synthetic sample lines are returned so the UI
 * renders without touching the real /var/log files.
 */
class LogAggregationService
{
    protected ?array $cachedSources = null;

    public function __construct(protected SudoExecutor $sudo) {}

    /** Base directory for Linux logs (default /var/log, overridable in config). */
    public function logRoot(): string
    {
        return (string) config('larapanel.paths.logs', '/var/log');
    }

    /**
     * Allowed roots for path-traversal protection. Every resolved log path
     * must live under one of these directories.
     */
    public function allowedRoots(): array
    {
        $roots = [$this->logRoot()];

        $laravelLogDir = dirname(storage_path('logs/laravel.log'));
        if (! in_array($laravelLogDir, $roots, true)) {
            $roots[] = $laravelLogDir;
        }

        $phpFpmDir = $this->logRoot() . '/php-fpm';
        if (is_dir($phpFpmDir) && ! in_array($phpFpmDir, $roots, true)) {
            $roots[] = $phpFpmDir;
        }

        return $roots;
    }

    /**
     * Build the list of log sources: key, label, path, type.
     * Glob-derived sources only appear if the file actually exists; fixed
     * sources (mysql, mail, laravel, audit) are always listed even when the
     * file does not exist yet (rendered as "sin datos").
     */
    public function sources(): array
    {
        if ($this->cachedSources !== null) {
            return $this->cachedSources;
        }

        $root = $this->logRoot();
        $sources = [];

        // Nginx — per-domain access/error + global logs.
        $nginxDir = $root . '/nginx';
        foreach ((array) glob($nginxDir . '/*.log') as $file) {
            $basename = basename($file);
            $sources['nginx/' . $basename] = [
                'key'   => 'nginx/' . $basename,
                'label' => 'Nginx — ' . $basename,
                'path'  => $file,
                'type'  => 'nginx',
            ];
        }

        // PHP-FPM — global fpm logs + per-domain error logs.
        $phpFiles = [];
        foreach (['php*-fpm.log', 'php-fpm/*.log', 'php-fpm/*.error.log'] as $pattern) {
            foreach ((array) glob($root . '/' . $pattern) as $file) {
                $phpFiles[$file] = true;
            }
        }
        foreach (array_keys($phpFiles) as $file) {
            $basename = basename($file);
            $sources['php-fpm/' . $basename] = [
                'key'   => 'php-fpm/' . $basename,
                'label' => 'PHP-FPM — ' . $basename,
                'path'  => $file,
                'type'  => 'php-fpm',
            ];
        }

        // MySQL error log (fixed paths).
        foreach ([$root . '/mysql/error.log', $root . '/mysql.log'] as $path) {
            $sources['mysql/' . basename($path)] = [
                'key'   => 'mysql/' . basename($path),
                'label' => 'MySQL — ' . basename($path),
                'path'  => $path,
                'type'  => 'mysql',
            ];
        }

        // Mail (postfix/mail.log).
        $sources['mail/mail.log'] = [
            'key'   => 'mail/mail.log',
            'label' => 'Mail — mail.log',
            'path'  => $root . '/mail.log',
            'type'  => 'mail',
        ];

        // Laravel application log.
        $sources['laravel/laravel.log'] = [
            'key'   => 'laravel/laravel.log',
            'label' => 'LaraPanel — laravel.log',
            'path'  => storage_path('logs/laravel.log'),
            'type'  => 'laravel',
        ];

        // Panel audit trail (DB).
        $sources['audit'] = [
            'key'   => 'audit',
            'label' => 'Panel — Auditoría (AuditLog)',
            'path'  => null,
            'type'  => 'audit',
        ];

        return $this->cachedSources = $sources;
    }

    /**
     * Tail every file matching a glob pattern, concatenating the tails.
     * Provided for API parity with LogService; the aggregate flow uses
     * per-source tailing.
     */
    public function tailGlob(string $pattern, int $lines = 200): string
    {
        $out = [];
        foreach ((array) glob($pattern) as $file) {
            $resolved = $this->normalizeAndCheckOrNull($file);
            if ($resolved === null) {
                continue;
            }
            $tail = $this->tailResolved($resolved, $lines);
            $out[] = '----- ' . basename($file) . ' -----' . "\n" . $tail['content'];
        }

        return implode("\n", $out);
    }

    /**
     * Classify a log line into error/warn/info/debug (empty for blank lines).
     */
    public function sniffLevel(string $line): string
    {
        if (trim($line) === '') {
            return '';
        }

        if (preg_match('/(\berror\b|\bfatal\b|\bpanic\b|\bcritical\b|\bexception\b|\balert\b|\bemerg\b|\bdenied\b|\bfailed\b)/i', $line)) {
            return 'error';
        }

        if (preg_match('/(\bwarn\b|\bwarning\b)/i', $line)) {
            return 'warn';
        }

        if (preg_match('/(\bdebug\b|\btrace\b|\bverbose\b)/i', $line)) {
            return 'debug';
        }

        return 'info';
    }

    /**
     * Best-effort timestamp extraction for chronological sorting.
     * Supports nginx/php-fpm bracket dates, ISO-8601 and syslog short dates.
     */
    public function extractTs(string $line): ?int
    {
        // [20/Sep/2026:14:03:21 +0000]
        if (preg_match('/\[(\d{1,2})\/([A-Za-z]{3})\/(\d{4}):(\d{2}):(\d{2}):(\d{2})(?:\s+([+\-]\d{4}))?\]/', $line, $m)) {
            $tz = isset($m[7]) && $m[7] !== '' ? ' ' . $m[7] : '';
            $ts = strtotime("{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:{$m[6]}{$tz}");

            return $ts === false ? null : $ts;
        }

        // 2026-09-20 14:03:21 / 2026-09-20T14:03:21...
        if (preg_match('/(\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2})/', $line, $m)) {
            $ts = strtotime($m[1]);

            return $ts === false ? null : $ts;
        }

        // Sep 20 14:03:21
        if (preg_match('/^([A-Za-z]{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})/', $line, $m)) {
            $ts = strtotime($m[1]);

            return $ts === false ? null : $ts;
        }

        return null;
    }

    /**
     * Aggregate all selected sources into a merged, filtered, reverse
     * chronological feed.
     *
     * @return array{sources: array, total: int, byLevel: array, feed: array}
     */
    public function aggregate(array $sourceKeys = [], array $filters = ['level' => null, 'query' => null], int $perSource = 120): array
    {
        $sources = $this->sources();

        if ($sourceKeys !== []) {
            $sources = array_values(array_filter($sources, fn ($s) => in_array($s['key'], $sourceKeys, true)));
        }

        $level = $filters['level'] ?? null;
        if (is_string($level) && ! in_array($level, ['error', 'warn', 'info', 'debug'], true)) {
            $level = null;
        }
        $query = trim((string) ($filters['query'] ?? ''));
        if ($query !== '') {
            $query = mb_strtolower($query, 'UTF-8');
        }

        $byLevel = ['error' => 0, 'warn' => 0, 'info' => 0, 'debug' => 0];
        $sourceOut = [];
        $merged = [];
        $seq = 0;

        foreach ($sources as $source) {
            $resolved = $this->resolveSource($source, $perSource);
            $sourceOut[] = $resolved;

            foreach ($resolved['lines'] as $entry) {
                if ($entry['level'] !== '' && isset($byLevel[$entry['level']])) {
                    $byLevel[$entry['level']]++;
                }

                $merged[] = [
                    'seq'    => $seq++,
                    'key'    => $source['key'],
                    'label'  => $source['label'],
                    'type'   => $source['type'],
                    'line'   => $entry['line'],
                    'level'  => $entry['level'],
                    'ts'     => $this->extractTs($entry['line']),
                ];
            }
        }

        if ($level !== null) {
            $merged = array_values(array_filter($merged, fn ($m) => $m['level'] === $level));
        }

        if ($query !== '') {
            $merged = array_values(array_filter($merged, fn ($m) => str_contains(mb_strtolower($m['line'], 'UTF-8'), $query)));
        }

        // Reverse-chronological: newest first, unparseable timestamps last.
        usort($merged, function (array $a, array $b): int {
            if ($a['ts'] === null && $b['ts'] === null) {
                return $a['seq'] <=> $b['seq'];
            }
            if ($a['ts'] === null) {
                return 1;
            }
            if ($b['ts'] === null) {
                return -1;
            }

            if ($a['ts'] === $b['ts']) {
                return $a['seq'] <=> $b['seq'];
            }

            return $b['ts'] <=> $a['ts'];
        });

        return [
            'sources' => $sourceOut,
            'total'   => count($merged),
            'byLevel' => $byLevel,
            'feed'    => array_values($merged),
        ];
    }

    // ───────────────────────────────────────────────────────────────────
    // Internals
    // ───────────────────────────────────────────────────────────────────

    /**
     * Resolve a fully qualified path for use with sudo tail. Returns a
     * realpath that is guaranteed to live inside one of the allowed roots.
     *
     * @throws \InvalidArgumentException when the path tries to escape.
     */
    protected function normalizeAndCheck(string $path): string
    {
        $path = str_replace("\0", '', $path);
        $resolved = realpath($path);

        if ($resolved === false) {
            throw new \InvalidArgumentException('La ruta de log no existe.');
        }

        foreach ($this->allowedRoots() as $root) {
            $root = rtrim($root, '/');
            if ($resolved === $root || str_starts_with($resolved, $root . '/')) {
                return $resolved;
            }
        }

        throw new \InvalidArgumentException('Acceso no autorizado: la ruta de log escapa del directorio permitido.');
    }

    protected function normalizeAndCheckOrNull(string $path): ?string
    {
        try {
            return $this->normalizeAndCheck($path);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveSource(array $source, int $lines): array
    {
        $base = [
            'key'   => $source['key'],
            'label' => $source['label'],
            'path'  => $source['path'],
            'type'  => $source['type'],
            'count' => 0,
        ];

        if ($source['type'] === 'audit') {
            return array_merge($base, ['lines' => $this->auditLines($lines)]);
        }

        if ($source['path'] === null) {
            return array_merge($base, ['lines' => []]);
        }

        if (! app()->isProduction()) {
            return array_merge($base, ['lines' => $this->syntheticLines($source['type'], $lines)]);
        }

        $resolved = $this->normalizeAndCheckOrNull($source['path']);
        if ($resolved === null || ! is_file($resolved)) {
            return array_merge($base, ['lines' => []]);
        }

        $tail = $this->tailResolved($resolved, $lines);

        return array_merge($base, [
            'count' => count($tail['lines']),
            'lines' => $tail['lines'],
        ]);
    }

    protected function tailResolved(string $resolved, int $lines): array
    {
        try {
            $result = $this->sudo->run(['tail', '-n', (string) max(1, $lines), $resolved], false);
        } catch (\Throwable) {
            return ['content' => '', 'lines' => []];
        }

        $content = $result->stdout ?? '';

        return [
            'content' => $content,
            'lines'   => $this->sniffLines($content),
        ];
    }

    protected function sniffLines(string $content): array
    {
        $lines = array_filter(explode("\n", $content), fn ($line) => trim((string) $line) !== '');

        return array_values(array_map(fn ($line) => [
            'line'  => rtrim((string) $line),
            'level' => $this->sniffLevel((string) $line),
        ], $lines));
    }

    protected function auditLines(int $lines): array
    {
        $out = [];

        foreach (AuditLog::latest()->limit($lines)->get() as $entry) {
            $level = match ($entry->severity) {
                'critical', 'error' => 'error',
                'warning'           => 'warn',
                'debug'             => 'debug',
                default             => 'info',
            };

            $line = sprintf(
                '%s [%s] %s %s (usuario:%s) %s',
                (string) $entry->created_at,
                (string) $entry->severity,
                $entry->action,
                (string) $entry->subject,
                $entry->user_id ?? '-',
                $entry->meta ? json_encode($entry->meta, JSON_UNESCAPED_SLASHES) : '',
            );

            $out[] = ['line' => rtrim($line), 'level' => $level];
        }

        return $out;
    }

    /**
     * Synthetic sample lines so the aggregate UI renders in local/dev without
     * touching real system logs (reglas_desarrollo: check app()->isProduction()).
     */
    protected function syntheticLines(string $type, int $count = 14): array
    {
        $brackets = fn (int $i) => now()->subSeconds($i)->format('d/M/Y:H:i:s') . ' +0000';
        $iso = fn (int $i) => now()->subSeconds($i)->tz('UTC')->format('Y-m-d\TH:i:s') . '.000000+00:00';
        $syslog = fn (int $i) => now()->subSeconds($i)->format('M d H:i:s');
        $phpBracket = fn (int $i) => now()->subSeconds($i)->format('d-M-Y H:i:s');

        $b2 = $brackets(2); $b5 = $brackets(5); $b9 = $brackets(9); $b12 = $brackets(12); $b14 = $brackets(14);
        $pb3 = $phpBracket(3); $pb7 = $phpBracket(7); $pb11 = $phpBracket(11); $pb16 = $phpBracket(16); $pb22 = $phpBracket(22);
        $i4 = $iso(4); $i9 = $iso(9); $i15 = $iso(15); $i20 = $iso(20); $i1 = $iso(1); $i8 = $iso(8); $i14 = $iso(14); $i19 = $iso(19);
        $sl6 = $syslog(6); $sl13 = $syslog(13); $sl18 = $syslog(18); $sl25 = $syslog(25);

        $pools = [
            'nginx' => [
                "127.0.0.1 - - [{$b2}] \"GET / HTTP/1.1\" 200 2345 \"-\" \"Mozilla/5.0\"",
                "192.168.1.20 - - [{$b5}] \"GET /api/v1/status HTTP/1.1\" 200 512 \"https://example.com/\" \"curl/8.0\"",
                "203.0.113.9 - - [{$b9}] \"POST /wp-login.php HTTP/1.1\" 403 150 \"-\" \"python-requests\"",
                "198.51.100.7 - - [{$b12}] \"GET /favicon.ico HTTP/1.1\" 404 153 \"-\" \"Mozilla/5.0\"",
                "203.0.113.9 - - [{$b14}] \"GET /xmlrpc.php HTTP/1.1\" 500 890 \"-\" \"python-requests\" [error] upstream timed out",
            ],
            'php-fpm' => [
                "[{$pb3}] NOTICE: [pool www] fpm is running, pid 1234",
                "[{$pb7}] WARNING: [pool www] server reached pm.max_children setting",
                "[{$pb11}] ERROR: [pool www] unable to open primary script: /var/www/vhost/index.php (No such file or directory)",
                "[{$pb16}] DEBUG: [pool www] child 8765 started",
                "[{$pb22}] NOTICE: [pool www] ready to handle connections",
            ],
            'mysql' => [
                "{$i4} 0 [Note] [MY-000000] InnoDB: Buffer pool(s) load completed",
                "{$i9} 4 [Warning] [MY-010000] [Server] insecure configuration for --secure-file-priv",
                "{$i15} 7 [ERROR] [MY-013183] [InnoDB] Failed to find valid data in table",
                "{$i20} 0 [Note] [MY-000000] Shutdown complete",
            ],
            'mail' => [
                "{$sl6} host postfix/smtp[3210]: 4F2A31A2BB: to=<user@example.com>, relay=mail.example.net, status=sent",
                "{$sl13} host postfix/smtpd[2193]: warning: hostname mail.spammer.net does not resolve",
                "{$sl18} host postfix/smtp[3219]: 4B7E11A2CB: to=<bounce@example.com>, relay=none, status=bounced",
                "{$sl25} host rspamd[998]: rspamd_check: message accepted, score=-1.50",
            ],
            'laravel' => [
                "{$i1} local.INFO: Panel autenticado.",
                "{$i8} local.ERROR: SQLSTATE[HY000]: General error en DatabaseController",
                "{$i14} local.DEBUG: consulta completada en 12ms",
                "{$i19} local.ERROR: Failed to connect to remote FTP server",
            ],
            'default' => [
                "{$i4} [INFO] proceso finalizado correctamente",
                "{$i9} [WARN] umbral de disco alcanzado",
                "{$i14} [ERROR] no se pudo completar la operación",
                "{$i19} [INFO] servicio recargado",
            ],
        ];

        $pool = $pools[$type] ?? $pools['default'];

        return array_map(fn ($line) => [
            'line'  => $line,
            'level' => $this->sniffLevel($line),
        ], array_slice($pool, 0, $count));
    }
}