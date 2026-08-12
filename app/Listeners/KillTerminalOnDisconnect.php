<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\TerminalSession;
use App\Services\TerminalSessionManager;
use Illuminate\Support\Str;
use Laravel\Reverb\Events\ChannelRemoved;
use Throwable;

/**
 * KillTerminalOnDisconnect — when the last subscriber leaves a private
 * terminal channel (tab closed, refresh, network drop) the PTY is destroyed
 * so no orphan shells linger on the box.
 */
class KillTerminalOnDisconnect
{
    public function __construct(
        protected TerminalSessionManager $manager,
    ) {}

    public function handle(ChannelRemoved $event): void
    {
        $name = $event->channel->name();

        if (! str_starts_with($name, 'private-terminal.')) {
            return;
        }

        try {
            $uuid = Str::after($name, 'private-terminal.');

            $session = TerminalSession::where('channel', $uuid)
                ->where('status', '!=', TerminalSession::STATUS_CLOSED)
                ->first();

            if (! $session) {
                return;
            }

            if ($this->manager->has($session->id)) {
                $this->manager->close($session->id);

                AuditLog::record('terminal.session.disconnect', "terminal:{$session->type}", [
                    'session_id' => $session->id,
                ]);
            }
        } catch (Throwable) {
            // best effort cleanup
        }
    }
}
