<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Server;
use App\Models\ServerHealthSnapshot;
use App\Models\ServerMetric;
use App\Models\UptimePing;
use App\Shell\ServerContext;
use App\Shell\SudoExecutor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * HealthScoreService — computes a composite server health score (0-100)
 * from weighted factors: CPU load, RAM, disk, swap, critical services,
 * SSL expiry and website uptime. Also exposes historical snapshots and
 * an hourly trend for the health gauge.
 */
class HealthScoreService
{
    protected const COMPONENT_WEIGHTS = [
        'cpu'      => 25,
        'ram'      => 20,
        'disk'     => 20,
        'swap'     => 5,
        'services' => 15,
        'ssl'      => 10,
        'uptime'   => 5,
    ];

    public function __construct(
        protected MonitoringService $monitoring,
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Compute the current composite health score.
     *
     * @return array{
     *     score:int, grade:string, computed_at:string,
     *     components:array<int, array{key:string,label:string,weight:int,value:mixed,points:int,note:string}>
     * }
     */
    public function score(?Server $server = null): array
    {
        if (!app()->isProduction()) {
            return $this->simulatedScore($server);
        }

        $snapshot = $this->monitoring->getSnapshot();

        $cpuRatio = $this->cpuLoadRatio($snapshot);
        $ramPct   = (float)($snapshot['ram']['percent'] ?? 0);
        $swapPct  = (float)($snapshot['ram']['swap_pct'] ?? 0);
        $diskPct  = $this->worstDiskPercent($snapshot['disk'] ?? []);

        $cpuThreshold = (float) config('larapanel.monitoring.alerts.cpu_threshold', 90);
        $ramThreshold = (float) config('larapanel.monitoring.alerts.ram_threshold', 90);
        $diskThreshold = (float) config('larapanel.monitoring.alerts.disk_threshold', 85);

        $failedServices = $this->failedCriticalServices();
        $sslExpiring    = $this->sslExpiringWithin7Days();
        $uptimeFailPct  = $this->uptimeFailurePercent();

        $components = [
            [
                'key'    => 'cpu',
                'label'  => 'Carga de CPU',
                'weight' => 25,
                'value'  => round($cpuRatio, 2),
                'points' => $this->descending($cpuRatio, 1.0, 4.0),
                'note'   => 'Load average <strong>' . number_format($cpuRatio, 2) . '</strong> por núcleo. Umbral de alerta CPU: ' . $cpuThreshold . '%',
            ],
            [
                'key'    => 'ram',
                'label'  => 'Uso de RAM',
                'weight' => 20,
                'value'  => $ramPct,
                'points' => $this->descending($ramPct, 60, $ramThreshold),
                'note'   => 'RAM usada: <strong>' . round($ramPct, 1) . '%</strong>. Umbral de alerta RAM: ' . $ramThreshold . '%',
            ],
            [
                'key'    => 'disk',
                'label'  => 'Uso de disco',
                'weight' => 20,
                'value'  => $diskPct,
                'points' => $this->descending($diskPct, 60, $diskThreshold),
                'note'   => 'Partición peor: <strong>' . round($diskPct, 1) . '%</strong>. Umbral de alerta disco: ' . $diskThreshold . '%',
            ],
            [
                'key'    => 'swap',
                'label'  => 'Uso de swap',
                'weight' => 5,
                'value'  => $swapPct,
                'points' => $this->descending($swapPct, 5, 30),
                'note'   => 'Swap ocupada: <strong>' . round($swapPct, 1) . '%</strong>',
            ],
            [
                'key'    => 'services',
                'label'  => 'Servicios críticos',
                'weight' => 15,
                'value'  => $failedServices,
                'points' => max(0, 100 - ($failedServices * 25)),
                'note'   => $failedServices > 0
                    ? '<strong>' . $failedServices . '</strong> servicio(s) detenido(s) o fallando'
                    : 'Todos los servicios críticos activos',
            ],
            [
                'key'    => 'ssl',
                'label'  => 'Certificados SSL',
                'weight' => 10,
                'value'  => $sslExpiring,
                'points' => max(0, 100 - ($sslExpiring * 20)),
                'note'   => $sslExpiring > 0
                    ? '<strong>' . $sslExpiring . '</strong> dominio(s) caducando en ≤ 7 días'
                    : 'Sin certificados próximos a caducar',
            ],
            [
                'key'    => 'uptime',
                'label'  => 'Uptime de sitios',
                'weight' => 5,
                'value'  => $uptimeFailPct,
                'points' => max(0, 100 - ($uptimeFailPct * 2)),
                'note'   => 'Fallos recientes (24h): <strong>' . round($uptimeFailPct, 2) . '%</strong>',
            ],
        ];

        return $this->assemble($components);
    }

    /**
     * Trend of average cpu/ram/disk usage per hour, usable by Chart.js.
     *
     * @return array{labels:array<int,string>, cpu:array<int,float>, ram:array<int,float>, disk:array<int,float>}
     */
    public function trend(int $hours = 24): array
    {
        if (!app()->isProduction()) {
            return $this->simulatedTrend($hours);
        }

        $since  = now()->subHours($hours);
        $buckets = [];

        try {
            $rows = ServerMetric::where('recorded_at', '>=', $since)
                ->orderBy('recorded_at')
                ->get(['recorded_at', 'cpu_usage', 'ram_usage', 'disk_usage']);

            foreach ($rows as $row) {
                $key = $row->recorded_at->format('Y-m-d H:00');
                $buckets[$key] ??= ['count' => 0, 'cpu' => 0, 'ram' => 0, 'disk' => 0];
                $buckets[$key]['count']++;
                $buckets[$key]['cpu']  += (float) $row->cpu_usage;
                $buckets[$key]['ram']  += (float) $row->ram_usage;
                $buckets[$key]['disk'] += (float) $row->disk_usage;
            }
        } catch (\Throwable $e) {
            Log::warning('HealthScoreService::trend error', ['error' => $e->getMessage()]);
        }

        $labels = [];
        $cpu = [];
        $ram = [];
        $disk = [];

        ksort($buckets);
        foreach ($buckets as $key => $bucket) {
            $labels[] = Carbon::parse($key)->format('H:i');
            $count    = max(1, $bucket['count']);
            $cpu[]    = round($bucket['cpu'] / $count, 1);
            $ram[]    = round($bucket['ram'] / $count, 1);
            $disk[]   = round($bucket['disk'] / $count, 1);
        }

        // Fill gaps so the frontend always gets a full hourly axis.
        $filled = $this->fillTrendGaps($labels, $cpu, $ram, $disk, $hours, $since);

        return $filled;
    }

    /**
     * Latest persisted health snapshots (default table 30).
     *
     * @return array<int, array{id:int, score:int, grade:string, components:array, created_at:string}>
     */
    public function latestSnapshots(int $limit = 30): array
    {
        return ServerHealthSnapshot::query()
            ->when($server = ServerContext::server(), fn ($q) => $q->where('server_id', $server->id))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (ServerHealthSnapshot $snap) {
                return [
                    'id'         => $snap->id,
                    'score'      => $snap->score,
                    'grade'      => $snap->grade,
                    'components' => $snap->components ?? [],
                    'created_at' => optional($snap->created_at)->format('d/m/Y H:i'),
                ];
            })
            ->all();
    }

    /**
     * Compute and persist a health snapshot, returning the saved model.
     */
    public function persistSnapshot(?Server $server = null): ServerHealthSnapshot
    {
        $result = $this->score($server);

        return ServerHealthSnapshot::create([
            'server_id'  => $server?->id,
            'score'      => $result['score'],
            'grade'      => $result['grade'],
            'components' => $result['components'],
        ]);
    }

    // ─── Factors ─────────────────────────────────────────────────────────────

    protected function cpuLoadRatio(array $snapshot): float
    {
        $cores = max(1, (int) ($snapshot['cpu']['cores'] ?? 1));
        $load  = (float) ($snapshot['load']['1m'] ?? 0);

        return round($load / $cores, 3);
    }

    protected function worstDiskPercent(array $disks): float
    {
        $worst = 0.0;
        foreach ($disks as $disk) {
            $worst = max($worst, (float) ($disk['percent'] ?? 0));
        }

        return $worst;
    }

    protected function failedCriticalServices(): int
    {
        $services = ['nginx', 'postfix', 'dovecot', 'redis-server', 'docker', 'fail2ban', 'mysql', 'mariadb'];

        // php-fpm: probe configured/known versions.
        $phpVersions = config('larapanel.server.php_versions', ['8.1', '8.2', '8.3', '8.4']);
        foreach ($phpVersions as $version) {
            $services[] = "php{$version}-fpm";
        }

        $failed = 0;

        foreach ($services as $service) {
            try {
                // Only count services that are actually installed on this host.
                if (!$this->serviceUnitExists($service)) {
                    continue;
                }

                $status = $this->sudo->serviceStatus($service);

                if (!$status['active']) {
                    $failed++;
                }
            } catch (\Throwable $e) {
                Log::debug('HealthScoreService service check failed', ['svc' => $service, 'error' => $e->getMessage()]);
            }
        }

        return $failed;
    }

    /**
     * Whether a systemd unit file exists for the given service name.
     */
    protected function serviceUnitExists(string $service): bool
    {
        try {
            $result = $this->sudo->run(['systemctl', 'list-unit-files', $service], checkExit: false);
            if (!$result->successful()) {
                return false;
            }

            foreach ($result->lines() as $line) {
                if (str_contains($line, $service . '.service')) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Fall through.
        }

        return false;
    }

    protected function sslExpiringWithin7Days(): int
    {
        try {
            return Domain::whereNotNull('ssl_expires_at')
                ->where('ssl_expires_at', '<=', now()->addDays(7))
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function uptimeFailurePercent(): float
    {
        try {
            $since = now()->subDay();

            $total  = UptimePing::where('created_at', '>=', $since)->count();
            if ($total === 0) {
                return 0.0;
            }

            $failed = UptimePing::where('created_at', '>=', $since)
                ->where('status', '!=', 'up')
                ->count();

            return round(($failed / $total) * 100, 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Linear falloff: 100 points at/under $good, 0 points at/over $bad.
     */
    protected function descending(float $value, float $good, float $bad): int
    {
        if ($value <= $good) {
            return 100;
        }
        if ($value >= $bad || $bad <= $good) {
            return 0;
        }

        return (int) round(100 * (($bad - $value) / ($bad - $good)));
    }

    /**
     * Combine weighted component points into the final score + grade.
     */
    protected function assemble(array $components): array
    {
        $score = 0;
        foreach ($components as $component) {
            $score += $component['points'] * ($component['weight'] / 100);
        }
        $score = (int) round($score);

        return [
            'score'      => $score,
            'grade'      => $this->grade($score),
            'computed_at'=> now()->toIso8601String(),
            'components' => $components,
        ];
    }

    protected function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            $score >= 50 => 'E',
            default      => 'F',
        };
    }

    protected function fillTrendGaps(array $labels, array $cpu, array $ram, array $disk, int $hours, Carbon $since): array
    {
        if (count($labels) >= $hours) {
            return compact('labels', 'cpu', 'ram', 'disk');
        }

        $full = [];
        for ($i = 0; $i < $hours; $i++) {
            $label = $since->copy()->addHours($i)->format('H:i');
            $idx   = array_search($label, $labels, true);

            $full['labels'][] = $label;
            $full['cpu'][]    = $idx === false ? 0.0 : $cpu[$idx];
            $full['ram'][]    = $idx === false ? 0.0 : $ram[$idx];
            $full['disk'][]   = $idx === false ? 0.0 : $disk[$idx];
        }

        return [
            'labels' => $full['labels'],
            'cpu'    => $full['cpu'],
            'ram'    => $full['ram'],
            'disk'   => $full['disk'],
        ];
    }

    // ─── Dev Simulation (deterministic) ──────────────────────────────────────

    protected function simulatedScore(?Server $server = null): array
    {
        $seed = (int) floor(time() / 3600); // changes once per hour
        mt_srand($seed);

        $cpuRatio = 1.15 + mt_rand(0, 40) / 100;
        $ramPct   = mt_rand(35, 72);
        $swapPct  = mt_rand(0, 12);
        $diskPct  = mt_rand(45, 78);

        mt_srand();

        $failedServices = 0;
        $sslExpiring    = 0;
        $uptimeFailPct  = 0.0;

        $cpuThreshold = (float) config('larapanel.monitoring.alerts.cpu_threshold', 90);
        $ramThreshold = (float) config('larapanel.monitoring.alerts.ram_threshold', 90);
        $diskThreshold = (float) config('larapanel.monitoring.alerts.disk_threshold', 85);

        $components = [
            [
                'key'    => 'cpu',
                'label'  => 'Carga de CPU',
                'weight' => 25,
                'value'  => round($cpuRatio, 2),
                'points' => $this->descending($cpuRatio, 1.0, 4.0),
                'note'   => 'Carga simulada: <strong>' . number_format($cpuRatio, 2) . '</strong> por núcleo',
            ],
            [
                'key'    => 'ram',
                'label'  => 'Uso de RAM',
                'weight' => 20,
                'value'  => $ramPct,
                'points' => $this->descending($ramPct, 60, $ramThreshold),
                'note'   => 'RAM simulada: <strong>' . $ramPct . '%</strong>',
            ],
            [
                'key'    => 'disk',
                'label'  => 'Uso de disco',
                'weight' => 20,
                'value'  => $diskPct,
                'points' => $this->descending($diskPct, 60, $diskThreshold),
                'note'   => 'Disco simulado: <strong>' . $diskPct . '%</strong>',
            ],
            [
                'key'    => 'swap',
                'label'  => 'Uso de swap',
                'weight' => 5,
                'value'  => $swapPct,
                'points' => $this->descending($swapPct, 5, 30),
                'note'   => 'Swap simulada: <strong>' . $swapPct . '%</strong>',
            ],
            [
                'key'    => 'services',
                'label'  => 'Servicios críticos',
                'weight' => 15,
                'value'  => $failedServices,
                'points' => 100,
                'note'   => 'Todos los servicios críticos activos (simulado)',
            ],
            [
                'key'    => 'ssl',
                'label'  => 'Certificados SSL',
                'weight' => 10,
                'value'  => $sslExpiring,
                'points' => 100,
                'note'   => 'Sin certificados próximos a caducar (simulado)',
            ],
            [
                'key'    => 'uptime',
                'label'  => 'Uptime de sitios',
                'weight' => 5,
                'value'  => $uptimeFailPct,
                'points' => 100,
                'note'   => 'Uptime 100% en las últimas 24h (simulado)',
            ],
        ];

        return $this->assemble($components);
    }

    protected function simulatedTrend(int $hours = 24): array
    {
        $seed = (int) floor(time() / 3600);
        mt_srand($seed);

        $labels = [];
        $cpu = [];
        $ram = [];
        $disk = [];

        for ($i = 0; $i < $hours; $i++) {
            $labels[] = now()->subHours($hours - $i)->format('H:i');
            $t        = $i / max(1, $hours - 1);
            $cpu[]    = round(20 + sin($t * 4) * 15 + mt_rand(-4, 8), 1);
            $ram[]    = round(45 + cos($t * 3.5) * 10 + mt_rand(-3, 6), 1);
            $disk[]   = round(50 + mt_rand(-2, 4), 1);
        }

        mt_srand();

        return compact('labels', 'cpu', 'ram', 'disk');
    }
}