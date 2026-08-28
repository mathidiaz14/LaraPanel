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
 * TerminateAccountJob — Permanently removes a hosting account and ALL of its
 * resources (domains + vhosts + files, databases, email, ftp, cron), then
 * deletes the User row.
 *
 * Idempotent: if the user no longer exists the job simply returns, so a
 * duplicate dispatch cannot fail or double-delete anything.
 */
class TerminateAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(
        public readonly int $userId,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
    ) {}

    public function handle(
        DomainService      $domainService,
        CronService        $cronService,
        EmailService       $emailService,
        FtpService         $ftpService,
        DatabaseService    $databaseService,
        ForceLogoutService $forceLogout,
    ): void {
        $user  = User::find($this->userId);
        $email = $this->email ?? $user?->email ?? 'unknown';

        // Idempotency: already terminated/deleted -> nothing to do.
        if (!$user) {
            return;
        }

        // 1. Domains — remove vhosts AND document root files.
        foreach ($user->domains as $domain) {
            try {
                $domainService->delete($domain, true);
            } catch (\Throwable $e) {
                Log::error('TerminateAccountJob: failed to delete domain', [
                    'domain_id' => $domain->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // 2. Databases — drop schema + mysql user.
        foreach ($user->databases as $database) {
            try {
                $databaseService->delete($database);
            } catch (\Throwable $e) {
                Log::error('TerminateAccountJob: failed to delete database', [
                    'database_id' => $database->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // 3. Email accounts — remove mailboxes.
        foreach ($user->emailAccounts as $account) {
            try {
                $emailService->delete($account);
            } catch (\Throwable $e) {
                Log::error('TerminateAccountJob: failed to delete email account', [
                    'email_id' => $account->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // 4. FTP accounts — remove.
        foreach ($user->ftpAccounts as $ftp) {
            try {
                $ftpService->delete($ftp);
            } catch (\Throwable $e) {
                Log::error('TerminateAccountJob: failed to delete ftp account', [
                    'ftp_id' => $ftp->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        // 5. Cron jobs — remove and re-sync system crontab.
        foreach ($user->cronJobs as $cron) {
            try {
                $cronService->delete($cron);
            } catch (\Throwable $e) {
                Log::error('TerminateAccountJob: failed to delete cron job', [
                    'cron_id' => $cron->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 6. Force-logout any lingering sessions / websocket connections.
        try {
            $forceLogout->logoutUser($user->id);
        } catch (\Throwable $e) {
            Log::error('TerminateAccountJob: failed to force logout sessions', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        AuditLog::record(
            'account.terminate',
            $email,
            [
                'user_id' => $user->id,
                'name'    => $this->name ?? $user->name,
            ],
            'warning',
            $user->id,
        );

        // 7. Finally delete the user row itself.
        $user->delete();
    }
}
