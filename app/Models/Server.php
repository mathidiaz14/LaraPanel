<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Server extends Model
{
    protected $fillable = [
        'user_id', 'name', 'hostname', 'port', 'username', 'notes',
        'auth_type', 'ssh_private_key', 'ssh_password',
        'status', 'last_ping_at', 'latency_ms', 'os_info',
        'is_local', 'is_active',
    ];

    protected $casts = [
        'port'        => 'integer',
        'latency_ms'  => 'integer',
        'os_info'     => 'array',
        'is_local'    => 'boolean',
        'is_active'   => 'boolean',
        'last_ping_at'=> 'datetime',
    ];

    // ── Encrypted attributes ──────────────────────────────────────────────────

    public function setSshPrivateKeyAttribute(?string $value): void
    {
        $this->attributes['ssh_private_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getSshPrivateKeyAttribute(?string $value): ?string
    {
        if ($value === null) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setSshPasswordAttribute(?string $value): void
    {
        $this->attributes['ssh_password'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getSshPasswordAttribute(?string $value): ?string
    {
        if ($value === null) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'online'  => 'badge-success',
            'offline' => 'badge-danger',
            default   => 'badge-secondary',
        };
    }

    public function statusIcon(): string
    {
        return match ($this->status) {
            'online'  => 'fa-circle-check',
            'offline' => 'fa-circle-xmark',
            default   => 'fa-circle-question',
        };
    }

    public function displayName(): string
    {
        return $this->is_local
            ? '📍 ' . $this->name . ' (local)'
            : $this->name;
    }

    /**
     * Build a label like "root@192.168.1.10:22"
     */
    public function connectionString(): string
    {
        return "{$this->username}@{$this->hostname}:{$this->port}";
    }

    /**
     * Scope to servers belonging to the authenticated user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)->where('is_active', true);
    }
}
