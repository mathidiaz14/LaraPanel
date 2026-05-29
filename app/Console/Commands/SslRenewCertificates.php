<?php

namespace App\Console\Commands;

use App\Services\SslService;
use Illuminate\Console\Command;

class SslRenewCertificates extends Command
{
    protected $signature   = 'ssl:renew {--force : Force renewal even if not expiring soon}';
    protected $description = 'Renew SSL certificates expiring within 30 days (Let\'s Encrypt auto-renewal)';

    public function handle(SslService $sslService): int
    {
        $this->info('🔒 LaraPanel SSL — Checking certificates for renewal...');

        $results = $sslService->renewAll();

        if (!empty($results['renewed'])) {
            $this->info('✅ Renewed: ' . implode(', ', $results['renewed']));
        }

        if (!empty($results['failed'])) {
            foreach ($results['failed'] as $fail) {
                $this->error("❌ Failed [{$fail['domain']}]: {$fail['error']}");
            }
        }

        if (!empty($results['skipped'])) {
            $this->line('⏭ Skipped ' . count($results['skipped']) . ' cert(s) (no domain).');
        }

        if (empty($results['renewed']) && empty($results['failed'])) {
            $this->line('✓ No certificates need renewal at this time.');
        }

        return empty($results['failed']) ? self::SUCCESS : self::FAILURE;
    }
}
