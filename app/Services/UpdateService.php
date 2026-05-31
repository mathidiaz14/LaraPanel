<?php

namespace App\Services;

use App\Shell\ShellExecutor;
use Illuminate\Support\Facades\Cache;

class UpdateService
{
    /**
     * Check if an update is available (with cache protection).
     * This avoids executing git fetch on every page load.
     */
    public static function isUpdateAvailableCached(): bool
    {
        return Cache::remember('larapanel_update_available', now()->addHours(4), function () {
            try {
                $executor = new ShellExecutor();
                $baseDir = base_path();
                
                // Fetch changes from remote quietly
                $executor->inDirectory($baseDir)->run(['git', 'fetch', 'origin'], false);
                
                $branchResult = $executor->inDirectory($baseDir)->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
                $branch = trim($branchResult->stdout);
                
                $localResult = $executor->inDirectory($baseDir)->run(['git', 'rev-parse', 'HEAD']);
                $local = trim($localResult->stdout);
                
                $remoteResult = $executor->inDirectory($baseDir)->run(['git', 'rev-parse', "origin/{$branch}"]);
                $remote = trim($remoteResult->stdout);
                
                return $local !== $remote;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /**
     * Clear the update cache (used after running updates or manually checking).
     */
    public static function clearCache(): void
    {
        Cache::forget('larapanel_update_available');
    }
}
