<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UptimeMonitor;
use App\Models\UptimePing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\UptimeAlert;
use App\Shell\ServerContext;
use App\Shell\SudoExecutor;

class CheckUptime extends Command
{
    protected $signature = 'larapanel:uptime';
    protected $description = 'Check all uptime monitors';

    public function handle(SudoExecutor $sudo)
    {
        $monitors = UptimeMonitor::where('status', '!=', 'paused')
            ->where('server_id', ServerContext::serverId())
            ->get();

        foreach ($monitors as $monitor) {
            // Check if it's time to run based on interval
            if ($monitor->last_checked_at && $monitor->last_checked_at->addMinutes($monitor->interval_minutes) > now()) {
                continue;
            }

            $startTime = microtime(true);
            $isUp = false;
            $errorMsg = null;

            if ($monitor->type === 'http') {
                try {
                    $response = Http::timeout(10)->get($monitor->target);
                    $isUp = $response->successful();
                    if (!$isUp) {
                        $errorMsg = "HTTP Status: " . $response->status();
                    }
                } catch (\Exception $e) {
                    $isUp = false;
                    $errorMsg = $e->getMessage();
                }
            } elseif ($monitor->type === 'docker') {
                try {
                    $result = $sudo->execute("docker inspect -f '{{.State.Status}}' " . escapeshellarg($monitor->target));
                    $isUp = trim($result) === 'running';
                    if (!$isUp) {
                        $errorMsg = "Docker status: " . trim($result);
                    }
                } catch (\Exception $e) {
                    $isUp = false;
                    $errorMsg = $e->getMessage();
                }
            }

            $responseTime = round((microtime(true) - $startTime) * 1000);

            // Log the ping
            UptimePing::create([
                'uptime_monitor_id' => $monitor->id,
                'status' => $isUp ? 'up' : 'down',
                'response_time_ms' => $responseTime,
            ]);

            $previousStatus = $monitor->status;
            $currentStatus = $isUp ? 'up' : 'down';

            $monitor->update([
                'status' => $currentStatus,
                'last_checked_at' => now(),
                'last_error' => $errorMsg,
            ]);

            // Alert if status changed and not from pending
            if ($previousStatus !== 'pending' && $previousStatus !== $currentStatus) {
                if ($monitor->user) {
                    Mail::to($monitor->user->email)->send(new UptimeAlert($monitor, $currentStatus, $errorMsg));
                }
            }
        }
    }
}
