<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ForceLogoutService — Invalidates all active web sessions for a given user.
 *
 * This is used when an account is suspended, terminated or its role changes,
 * so the user can no longer act with a previously authenticated session.
 */
class ForceLogoutService
{
    /**
     * Delete every persisted web session for the user and best-effort clear
     * any Reverb (websocket) presence keys so live connections are dropped.
     */
    public function logoutUser(int $userId): void
    {
        try {
            DB::table('sessions')->where('user_id', $userId)->delete();
        } catch (\Throwable $e) {
            Log::error('ForceLogoutService: failed to purge sessions', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }

        // Best-effort: forget any Reverb presence cache entries for this user.
        // Reverb stores presence under keys such as "reverb:preset:<channel>:<user_id>";
        // we cannot enumerate all channels reliably, so we drop the most common guesses.
        try {
            Cache::forget('reverb:preset-user-' . $userId);
            Cache::forget('reverb:user:' . $userId);
            Cache::forget('reverb:presence:' . $userId);
        } catch (\Throwable $e) {
            // Non-critical: ignore cache backend errors.
        }
    }
}
