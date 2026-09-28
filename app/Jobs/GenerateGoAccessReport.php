<?php

namespace App\Jobs;

use App\Models\AuditLog;
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

    public function backoff(): array
    {
        return [30];
    }

    public function __construct(
        public readonly int $domainId,
    ) {}

    public function handle(GoAccessService $service): void
    {
        $domain = Domain::findOrFail($this->domainId);

        $service->generateReport($domain);
    }

    public function failed(\Throwable $e): void
    {
        $domain = Domain::find($this->domainId);

        Log::error(
            "[GoAccess] Report generation permanently failed for domain {$this->domainId}: " . $e->getMessage(),
            ['domain_id' => $this->domainId]
        );

        AuditLog::record('goaccess.report.failed', $domain?->name ?? (string) $this->domainId, [
            'domain_id' => $this->domainId,
            'error'     => $e->getMessage(),
        ], 'warning');
    }
}
