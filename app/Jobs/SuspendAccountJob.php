<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\CronService;
use App\Services\DatabaseService;
use App\Services\DomainService;
use App\Services\EmailService;
use App\Services\ForceLogoutService;
use App\Services\FtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SuspendAccountJob — Freezes a hosting account WITHOUT deleting any data.
 *
 * Disables Nginx vhosts, pauses cron jobs, suspends email mailboxes and FTP
 * accounts and freezes databases. All operations are idempotent: re-running
 * the job simply re-applies the disabled state and is therefore safe.
 */
class SuspendAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public readonly int $userId) {}

    public function handle(
        DomainService      $domainService,
        CronService        $cronService,
        EmailService       $emailService,
        FtpService         $ftpService,
        DatabaseService    $databaseService,
        ForceLogoutService $forceLogout,
    ): void {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        $reason = $user->suspension_reason ?? 'Suspended via account lifecycle';

        // 1. Domains — disable Nginx vhosts (files/document roots kept intact).
        foreach ($user->domains as $domain) {
            try {
                if ($domain->is_active) {
                    $domainService->suspend($domain, $reason);
                }
            } catch (\Throwable $e) {
                Log::error('SuspendAccountJob: failed to suspend domain', [
                    'domain_id' => $domain->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // 2. Cron jobs — pause (toggle off only when currently active).
        foreach ($user->cronJobs as $cron) {
            try {
                if ($cron->is_active) {
                    $cronService->toggleStatus($cron);
                }
            } catch (\Throwable $e) {
                Log::error('SuspendAccountJob: failed to pause cron job', [
                    'cron_id' => $cron->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 3. Email accounts — suspend mailbox.
        foreach ($user->emailAccounts as $email) {
            try {
                if ($email->is_active) {
                    $emailService->toggleStatus($email);
                }
            } catch (\Throwable $e) {
                Log::error('SuspendAccountJob: failed to suspend email account', [
                    'email_id' => $email->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // 4. FTP accounts — disable (no dedicated service method; freeze flag).
        foreach ($user->ftpAccounts as $ftp) {
            try {
                if ($ftp->is_active) {
                    $ftp->update(['is_active' => false]);
                }
            } catch (\Throwable $e) {
                Log::error('SuspendAccountJob: failed to disable ftp account', [
                    'ftp_id' => $ftp->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        // 5. Databases — freeze (no dedicated disable method; keep schema/data).
        foreach ($user->databases as $database) {
            try {
                if ($database->is_active) {
                    $database->update(['is_active' => false]);
                }
            } catch (\Throwable $e) {
                Log::error('SuspendAccountJob: failed to freeze database', [
                    'database_id' => $database->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // 6. Force-logout any active web sessions / websocket connections.
        try {
            $forceLogout->logoutUser($user->id);
        } catch (\Throwable $e) {
            Log::error('SuspendAccountJob: failed to force logout sessions', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        AuditLog::record(
            'account.suspend',
            $user->email,
            [
                'user_id'   => $user->id,
                'resources' => 'domains,crons,emails,ftp,databases',
                'reason'    => $reason,
            ],
            'info',
            $user->id,
        );
    }
}
