<?php

namespace App\Console\Commands;

use App\Services\UptimeProvisioner;
use Illuminate\Console\Command;

class SyncUptimeMonitors extends Command
{
    protected $signature = 'larapanel:uptime-sync';
    protected $description = 'Auto-enroll uptime monitors for every hosted domain and Docker container';

    public function handle(UptimeProvisioner $provisioner)
    {
        $added  = $provisioner->syncAll();
        $pruned = $provisioner->pruneStale();

        $this->info("Uptime sync finished: {$added} monitor(s) added, {$pruned} stale auto monitor(s) removed.");

        return self::SUCCESS;
    }
}
