<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fail2banEvent extends Model
{
    protected $fillable = [
        'user_id', 'jail', 'ip_address', 'action',
        'reason', 'ban_count', 'banned_at', 'expires_at', 'initiated_by',
    ];

    protected $casts = [
        'ban_count'  => 'integer',
        'banned_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    public function actionBadgeClass(): string
    {
        return match($this->action) {
            'ban'       => 'badge-danger',
            'unban'     => 'badge-success',
            'whitelist' => 'badge-muted',
            default     => 'badge-muted',
        };
    }
}
