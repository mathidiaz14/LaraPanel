<?php

namespace App\Shell;

use App\Models\Server;
use Illuminate\Support\Facades\Auth;

/**
 * ServerContext — Singleton that resolves the currently active server
 * for the current request/session.
 *
 * Usage:
 *   ServerContext::server()    → Server model | null (local)
 *   ServerContext::isRemote()  → bool
 *   ServerContext::executor()  → RemoteShellExecutor | null
 *   ServerContext::switchTo(int $serverId)
 *   ServerContext::switchToLocal()
 */
class ServerContext
{
    private static ?Server $current = null;
    private static bool $resolved   = false;

    /**
     * Resolve the active server from session. Call once per request
     * (done in AppServiceProvider::boot).
     */
    public static function resolve(): void
    {
        if (self::$resolved) return;
        self::$resolved = true;

        $serverId = session('active_server_id');

        if (!$serverId || !Auth::check()) {
            self::$current = null;
            return;
        }

        try {
            $server = Server::where('id', $serverId)
                ->where('user_id', Auth::id())
                ->where('is_active', true)
                ->where('is_local', false)
                ->first();

            self::$current = $server; // null if not found/unauthorized
        } catch (\Throwable) {
            self::$current = null;
        }
    }

    /**
     * Get the active remote Server model, or null if local.
     */
    public static function server(): ?Server
    {
        self::resolve();
        return self::$current;
    }

    /**
     * True when a remote server is active.
     */
    public static function isRemote(): bool
    {
        self::resolve();
        return self::$current !== null;
    }

    /**
     * Get a RemoteShellExecutor for the active server, or null if local.
     */
    public static function executor(): ?RemoteShellExecutor
    {
        self::resolve();
        if (self::$current === null) return null;
        return new RemoteShellExecutor(self::$current);
    }

    /**
     * Switch the active server for the current session.
     */
    public static function switchTo(int $serverId): void
    {
        session(['active_server_id' => $serverId]);
        self::$resolved = false;
        self::resolve();
    }

    /**
     * Switch back to the local server.
     */
    public static function switchToLocal(): void
    {
        session()->forget('active_server_id');
        self::$current  = null;
        self::$resolved = true;
    }

    /**
     * Get a human-readable label for the current context.
     */
    public static function label(): string
    {
        self::resolve();
        if (self::$current === null) {
            return '📍 Este servidor (local)';
        }
        return '🖥 ' . self::$current->name . ' (' . self::$current->hostname . ')';
    }

    /**
     * Reset for testing purposes.
     * @internal
     */
    public static function reset(): void
    {
        self::$current  = null;
        self::$resolved = false;
    }
}
