<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Shell\ShellExecutor;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;

/**
 * CacheService — gestor visual de Redis y Memcached.
 *
 * Todos los comandos pasan por el executor (arrays → Symfony Process con
 * escape de argumentos). Redis se gestiona con `redis-cli`; Memcached es
 * solo-lectura vía `memcached-tool` (el flush requiere telnet interactivo).
 */
class CacheService
{
    public function __construct(
        protected SudoExecutor $sudo,
        protected ShellExecutor $shell,
    ) {}

    /**
     * Snapshot completo de Redis (PING + INFO + claves + DBSIZE).
     */
    public function redisSnapshot(): array
    {
        if (!app()->isProduction()) {
            return $this->fakeRedisSnapshot();
        }

        if (!$this->redisPing()) {
            return [
                'connected' => false,
                'info'      => [],
                'keys'      => [],
                'dbsize'    => 0,
            ];
        }

        return [
            'connected' => true,
            'info'      => $this->redisInfo(),
            'keys'      => $this->redisKeys(),
            'dbsize'    => $this->redisDbSize(),
        ];
    }

    public function redisPing(): bool
    {
        try {
            $result = $this->sudo->run(['redis-cli', '--no-auth-warning', 'PING'], checkExit: false);

            return $result->successful() && strtoupper(trim($result->stdout)) === 'PONG';
        } catch (\Throwable $e) {
            Log::warning('CacheService: redis PING falló', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * INFO completo parseado en secciones (Server, Clients, Memory, Stats, Keyspace).
     */
    public function redisInfo(): array
    {
        try {
            $result = $this->sudo->run(['redis-cli', '--no-auth-warning', 'INFO'], checkExit: false);

            if (!$result->successful()) {
                return [];
            }

            return $this->parseInfoSections($result->stdout);
        } catch (\Throwable $e) {
            Log::warning('CacheService: redis INFO falló', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Lista de claves mediante un único SCAN acotado (`--scan --count 1000`).
     * La paginación para la vista se hace en PHP.
     *
     * @return array<int,string>
     */
    public function redisKeys(): array
    {
        try {
            $result = $this->sudo->run(['redis-cli', '--scan', '--count', '1000'], checkExit: false);

            if (!$result->successful()) {
                return [];
            }

            return array_values(array_filter(array_map('trim', explode("\n", $result->stdout))));
        } catch (\Throwable $e) {
            Log::warning('CacheService: redis SCAN falló', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function redisDbSize(): int
    {
        try {
            $result = $this->sudo->run(['redis-cli', '--no-auth-warning', 'DBSIZE'], checkExit: false);

            return $result->successful() ? (int) trim($result->stdout) : 0;
        } catch (\Throwable $e) {
            Log::warning('CacheService: redis DBSIZE falló', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    public function redisDeleteKey(string $key): bool
    {
        if (trim($key) === '') {
            return false;
        }

        try {
            $result = $this->sudo->run(['redis-cli', '--no-auth-warning', 'DEL', $key], checkExit: false);
            $deleted = $result->successful() && trim($result->stdout) === '1';

            if ($deleted) {
                $this->audit('cache.redis.key_deleted', $key);
            }

            return $deleted;
        } catch (\Throwable $e) {
            Log::warning('CacheService: redis DEL falló', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function redisFlush(): bool
    {
        try {
            $result = $this->sudo->run(['redis-cli', '--no-auth-warning', 'FLUSHALL'], checkExit: false);
            $flushed = $result->successful();

            if ($flushed) {
                $this->audit('cache.redis.flush', 'Redis');
            }

            return $flushed;
        } catch (\Throwable $e) {
            Log::warning('CacheService: redis FLUSHALL falló', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Estadísticas de Memcached vía `memcached-tool`. Si el binario no está
     * instalado (o falla) se devuelve un aviso sin datos; el flush no se
     * soporta desde el panel porque exige telnet interactivo.
     */
    public function memcachedSnapshot(): array
    {
        if (!app()->isProduction()) {
            return $this->fakeMemcachedSnapshot();
        }

        try {
            $result = $this->shell->run(['memcached-tool', '127.0.0.1:11211', 'stats'], checkExit: false);

            if ($result->successful() && trim($result->stdout) !== '') {
                return [
                    'available' => true,
                    'message'   => '',
                    'stats'     => $this->parseMemcachedStats($result->stdout),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('CacheService: memcached-tool falló', ['error' => $e->getMessage()]);
        }

        return [
            'available' => false,
            'message'   => 'memcached-tool no está instalado o no pudo conectar. El vaciado de Memcached requiere acceso telnet interactivo a 127.0.0.1:11211.',
            'stats'     => [],
        ];
    }

    /**
     * Parsea la salida multilínea de INFO en secciones por minúsculas.
     */
    protected function parseInfoSections(string $raw): array
    {
        $sections = [];
        $current  = null;

        foreach (explode("\n", $raw) as $line) {
            $line = rtrim($line);

            if (str_starts_with($line, '#')) {
                $current = strtolower(trim(substr($line, 1)));
                $sections[$current] = [];
                continue;
            }

            if ($current === null || $line === '') {
                continue;
            }

            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }

            $sections[$current][substr($line, 0, $pos)] = substr($line, $pos + 1);
        }

        return $sections;
    }

    /**
     * Parsea la salida `key value` de memcached-tool stats.
     */
    protected function parseMemcachedStats(string $raw): array
    {
        $stats = [];

        foreach (explode("\n", $raw) as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if (count($parts) < 2) {
                continue;
            }

            $value = $parts[count($parts) - 1];

            if (is_numeric($value)) {
                $stats[$parts[0]] = $value;
            }
        }

        return $stats;
    }

    protected function audit(string $action, string $subject): void
    {
        try {
            AuditLog::record(action: $action, subject: $subject, severity: 'warning');
        } catch (\Throwable) {
            // Persistencia de auditoría best-effort (misma política que ShellExecutor)
        }
    }

    // ─── Dev Simulation ────────────────────────────────────────────────

    protected function fakeRedisSnapshot(): array
    {
        return [
            'connected' => true,
            'info' => [
                'server'   => ['redis_version' => '7.2.4', 'uptime_in_seconds' => '456789'],
                'clients'  => ['connected_clients' => '4'],
                'memory'   => [
                    'used_memory'            => '134217728',
                    'used_memory_human'      => '128.00M',
                    'maxmemory'              => '536870912',
                    'maxmemory_human'        => '512.00M',
                    'mem_fragmentation_ratio'=> '1.05',
                ],
                'stats'    => [
                    'total_connections_received' => '2048',
                    'total_commands_processed'   => '152000',
                    'keyspace_hits'              => '3210',
                    'keyspace_misses'            => '145',
                ],
                'keyspace' => ['db0' => 'keys=24,expires=12,avg_ttl=0'],
            ],
            'keys'   => [
                'laravel_database_laravel_cache_panel:plantilla',
                'laravel_database_session:abc123def456',
                'laravel_database_rate_limiter:login',
                'laravel_database_fw_conn_stats_1',
            ],
            'dbsize' => 24,
        ];
    }

    protected function fakeMemcachedSnapshot(): array
    {
        return [
            'available' => false,
            'message'   => 'Memcached no se gestiona en este entorno. El vaciado requiere acceso telnet a 127.0.0.1:11211.',
            'stats'     => [],
        ];
    }
}