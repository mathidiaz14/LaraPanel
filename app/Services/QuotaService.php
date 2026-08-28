<?php

namespace App\Services;

use App\Models\DatabaseInstance;
use App\Models\User;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;

/**
 * QuotaService — Real (soft) resource quota enforcement.
 *
 * Computes actual disk usage from the filesystem and MySQL so the plan's
 * disk limit (plans.disk_quota_bytes) is enforced against real data, not
 * a stored counter.
 */
class QuotaService
{
    /**
     * Sum real disk usage (bytes) across all of the user's resources:
     * web roots, databases and email mailboxes.
     */
    public function diskUsageBytes(User $user): int
    {
        $total = 0;

        // 1. Web roots of every domain (incl. soft-deleted, still on disk).
        foreach ($user->domains()->withTrashed()->get() as $domain) {
            $total += $this->dirSize($domain->document_root);
        }

        // 2. Databases (live information_schema sum, real data).
        foreach ($user->databases as $db) {
            $total += $this->databaseSize($db);
        }

        // 3. Email mailboxes (vmail directory per account).
        $vmail = config('larapanel.paths.vmail', '/var/vmail');
        foreach ($user->emailAccounts()->with('domain')->get() as $account) {
            if ($account->domain) {
                $path = $vmail . '/' . $account->domain->name . '/' . $account->username;
                $total += $this->dirSize($path);
            }
        }

        return $total;
    }

    /**
     * Plan disk limit in bytes, or null when unlimited (no plan / zero limit).
     */
    public function diskQuotaBytes(User $user): ?int
    {
        if (!$user->plan) {
            return null;
        }

        $bytes = (int) $user->plan->disk_quota_bytes;

        return $bytes > 0 ? $bytes : null;
    }

    /**
     * True when the user is within (or has no) disk quota.
     */
    public function withinDiskQuota(User $user): bool
    {
        $quota = $this->diskQuotaBytes($user);

        if ($quota === null) {
            return true;
        }

        return $this->diskUsageBytes($user) <= $quota;
    }

    /**
     * Throw a clear Spanish message when the user is over their disk quota.
     */
    public function enforceDiskQuota(User $user): void
    {
        $quota = $this->diskQuotaBytes($user);

        if ($quota === null) {
            return;
        }

        $usage = $this->diskUsageBytes($user);

        if ($usage > $quota) {
            $usedMb  = number_format(round($usage / 1048576, 1), 1, '.', '');
            $quotaMb = number_format(round($quota / 1048576, 1), 1, '.', '');

            throw new \RuntimeException(
                "Has superado la cuota de disco de tu plan ({$usedMb} MB usados de {$quotaMb} MB permitidos). Elimina archivos o amplía tu plan."
            );
        }
    }

    /**
     * Real size of a directory in bytes using `du -sb` (via SudoExecutor).
     */
    public function dirSize(?string $path): int
    {
        if (!$path || !is_dir($path)) {
            return 0;
        }

        try {
            $sudo   = app(SudoExecutor::class);
            $result = $sudo->run(['du', '-sb', $path], checkExit: false);

            if ($result->successful() && $result->stdout !== '') {
                return (int) explode("\t", trim($result->stdout))[0];
            }
        } catch (\Throwable $e) {
            Log::warning("QuotaService: du failed for {$path}: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Real size of a single MySQL database via information_schema.
     */
    protected function databaseSize(DatabaseInstance $db): int
    {
        if (!app()->isProduction()) {
            return (int) $db->size_bytes;
        }

        try {
            $sudo = app(SudoExecutor::class);
            $q    = "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = '{$db->db_name}'";
            $result = $sudo->run(['mysql', '-sN', '-e', $q], checkExit: false);

            if ($result->successful()) {
                return (int) trim($result->stdout);
            }
        } catch (\Throwable $e) {
            Log::warning("QuotaService: db size failed for {$db->db_name}: " . $e->getMessage());
        }

        return (int) $db->size_bytes;
    }
}
