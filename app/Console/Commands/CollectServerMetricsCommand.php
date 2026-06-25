<?php

namespace App\Console\Commands;

use App\Models\ServerMetric;
use App\Models\User;
use App\Notifications\ServerResourceAlert;
use App\Services\MonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectServerMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panel:collect-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect server metrics, save them historically, and alert if thresholds are exceeded';

    /**
     * Execute the console command.
     */
    public function handle(MonitoringService $monitoringService)
    {
        $snapshot = $monitoringService->snapshot();
        $services = $monitoringService->servicesStatus();

        if (empty($snapshot)) {
            $this->error('Could not retrieve metrics snapshot.');
            return;
        }

        $cpuUsage = $snapshot['cpu'] ?? 0;
        $ramUsage = $snapshot['ram']['usage'] ?? 0;
        $ramTotal = $snapshot['ram']['total'] ?? 0;
        $ramUsed = $snapshot['ram']['used'] ?? 0;
        $diskUsage = $snapshot['disk']['usage'] ?? 0;
        $diskTotal = $snapshot['disk']['total'] ?? 0;
        $diskUsed = $snapshot['disk']['used'] ?? 0;

        // Save metric historically
        ServerMetric::create([
            'cpu_usage' => $cpuUsage,
            'ram_usage' => $ramUsage,
            'ram_total' => $ramTotal,
            'ram_used'  => $ramUsed,
            'disk_usage'=> $diskUsage,
            'disk_total'=> $diskTotal,
            'disk_used' => $diskUsed,
            'net_in'    => 0, // Placeholder
            'net_out'   => 0, // Placeholder
            'load_1'    => (float) ($snapshot['loadavg'] ?? 0),
            'load_5'    => 0, // Placeholder
            'load_15'   => 0, // Placeholder
            'process_count' => 0, // Placeholder
            'services'  => $services,
            'recorded_at' => now(),
        ]);

        $this->info("Metrics collected: CPU {$cpuUsage}% | RAM {$ramUsage}%");

        // Threshold checks
        $thresholdCpu = config('larapanel.alerts.cpu_threshold', 90);
        $thresholdRam = config('larapanel.alerts.ram_threshold', 95);

        if ($cpuUsage > $thresholdCpu) {
            $this->notifyAdmins('CPU', $cpuUsage, "El uso de CPU alcanzó el {$cpuUsage}% (Umbral: {$thresholdCpu}%).");
        }

        if ($ramUsage > $thresholdRam) {
            $this->notifyAdmins('RAM', $ramUsage, "El uso de RAM alcanzó el {$ramUsage}% (Umbral: {$thresholdRam}%).");
        }
    }

    private function notifyAdmins(string $resource, float $usage, string $message)
    {
        Log::warning("ALERT: $message");

        $admins = User::role('admin')->get();
        if ($admins->isEmpty()) {
            // Fallback if no roles are set up properly, get the first user
            $admins = User::limit(1)->get();
        }

        foreach ($admins as $admin) {
            $admin->notify(new ServerResourceAlert($resource, $usage, $message));
        }
    }
}
