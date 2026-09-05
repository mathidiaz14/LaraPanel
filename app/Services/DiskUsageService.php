<?php

namespace App\Services;

use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * DiskUsageService — Directory size analysis via `du`.
 *
 * Runs as root (sudo) so the whole filesystem can be measured accurately
 * (vmail/mysql are not readable by the web user).
 *
 * Security: only paths under the allowed roots can be scanned. The path is
 * resolved with realpath() and prefix-checked before touching the shell.
 */
class DiskUsageService
{
    /** Roots that may be scanned, in display order. */
    public const ALLOWED_ROOTS = [
        '/',
        '/var/www',
        '/var/log',
        '/var/lib/mysql',
        '/var/lib/docker',
        '/var/larapanel/backups',
        '/home',
        '/tmp',
    ];

    protected const CACHE_TTL_SECONDS = 60;

    public function __construct(
        protected SudoExecutor $shell,
    ) {}

    /**
     * Allowed roots that actually exist on this server.
     *
     * @return string[]
     */
    public function availableRoots(): array
    {
        return array_values(array_filter(
            self::ALLOWED_ROOTS,
            fn($root) => is_dir($root)
        ));
    }

    /**
     * Scan one level of a directory tree (du -d 1), cached briefly.
     *
     * @return array{path:string, total:int, items:array[], scanned_at:string}
     */
    public function scan(string $path): array
    {
        $real = $this->validatePath($path);
        $key  = 'disk_usage_scan:' . md5($real);

        try {
            return Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($real) {
                return $this->doScan($real);
            });
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new \RuntimeException('Error al analizar la ruta: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * True when the given path is a scannable directory.
     */
    public function isScannable(string $path): bool
    {
        try {
            $this->validatePath($path);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Validate a user-provided path against the allowed roots.
     */
    public function validatePath(string $path): string
    {
        $real = realpath($path);

        if ($real === false || !is_dir($real)) {
            throw new \InvalidArgumentException("La ruta no existe o no es un directorio: [{$path}]");
        }

        foreach ($this->availableRoots() as $root) {
            if ($real === $root || str_starts_with($real, rtrim($root, '/') . '/')) {
                return $real;
            }
        }

        throw new \InvalidArgumentException("Ruta fuera de las zonas permitidas: [{$path}]");
    }

    /**
     * Run `du -B1 -d 1` and parse into sorted items.
     */
    protected function doScan(string $path): array
    {
        // Large trees (mysql, docker) can take a while — allow 2 minutes
        $result = $this->shell
            ->withTimeout(120)
            ->run(['du', '-B1', '-d', '1', $path], checkExit: false);

        $total   = 0;
        $entries = [];

        foreach ($result->lines() as $line) {
            if (!preg_match('/^(\d+)\t(.+)$/', $line, $m)) {
                continue; // skip permission-denied stderr noise / malformed lines
            }
            $bytes = (int)$m[1];
            $dir   = $m[2];

            if ($dir === $path) {
                $total = $bytes;
                continue;
            }

            $entries[] = [
                'path'  => $dir,
                'name'  => basename($dir),
                'bytes' => $bytes,
                'is_dir'=> is_dir($dir),
            ];
        }

        usort($entries, fn($a, $b) => $b['bytes'] <=> $a['bytes']);

        foreach ($entries as &$entry) {
            $entry['pct'] = $total > 0 ? round(($entry['bytes'] / $total) * 100, 1) : 0;
        }

        return [
            'path'       => $path,
            'total'      => $total,
            'items'      => $entries,
            'partial'    => !$result->successful(), // du exits non-zero on permission errors
            'scanned_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Partition usage from df (delegates to MonitoringService).
     */
    public function partitions(): array
    {
        try {
            return app(MonitoringService::class)->getDiskMetrics();
        } catch (Throwable) {
            return [];
        }
    }
}
