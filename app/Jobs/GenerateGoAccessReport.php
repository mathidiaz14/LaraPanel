<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\GoAccessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateGoAccessReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly int $domainId,
    ) {}

    public function handle(GoAccessService $service): void
    {
        $domain = Domain::findOrFail($this->domainId);

        try {
            $service->generateReport($domain);
        } catch (\Throwable $e) {
            Log::error("[GoAccess] Failed to generate report for {$domain->name}: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
