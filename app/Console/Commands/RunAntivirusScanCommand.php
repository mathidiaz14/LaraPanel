<?php

namespace App\Console\Commands;

use App\Models\AntivirusScan;
use App\Services\AntivirusService;
use Illuminate\Console\Command;

class RunAntivirusScanCommand extends Command
{
    protected $signature = 'antivirus:scan {scanId}';

    protected $description = 'Execute a persisted antivirus scan record (background worker).';

    public function handle(AntivirusService $antivirus): int
    {
        $scan = AntivirusScan::find($this->argument('scanId'));

        if (!$scan) {
            $this->error('Scan record not found.');
            return 1;
        }

        $antivirus->executeScan($scan);

        $this->info("Scan #{$scan->id} finished with status: {$scan->fresh()->status}");

        return 0;
    }
}
