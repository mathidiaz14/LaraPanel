<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TerminalSession extends Model
{
    protected $table = 'terminal_sessions';

    protected $fillable = [
        'user_id', 'type', 'server_id', 'token', 'channel', 'cwd',
        'status', 'error', 'started_at', 'last_activity_at', 'ended_at',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ATTACHED = 'attached';

    public const STATUS_CLOSED = 'closed';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    // ── Factory / Helpers ─────────────────────────────────────────────────────

    public static function createForUser(
        int $userId,
        string $type = 'local',
        ?int $serverId = null,
        ?string $cwd = null,
    ): static {
        return static::create([
            'user_id' => $userId,
            'type' => $type === 'ssh' ? 'ssh' : 'local',
            'server_id' => $type === 'ssh' ? $serverId : null,
            'token' => Str::random(64),
            'channel' => (string) Str::uuid(),
            'cwd' => $cwd,
            'status' => self::STATUS_PENDING,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    public function channelName(): string
    {
        return "private-terminal.{$this->channel}";
    }

    /**
     * Shared authorization for the private WebSocket channel.
     *
     * Used both by the Broadcast::channel authentication callback and the
     * Reverb-side message handler. Access requires an admin user owning an
     * still-open session.
     */
    public static function canJoin(?User $user, string $channel): bool
    {
        if (! $user instanceof User || ! $user->isAdmin()) {
            return false;
        }

        $session = static::where('channel', $channel)->first();

        if (! $session || ! $session->isActive()) {
            return false;
        }

        return (int) $session->user_id === (int) $user->id;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ATTACHED], true);
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }

    public function close(?string $error = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_CLOSED,
            'error' => $error,
            'ended_at' => now(),
        ])->save();
    }

    /**
     * Scope: open sessions belonging to the given user.
     */
    public function scopeOpenForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)->where('status', '!=', self::STATUS_CLOSED);
    }
}
