<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpamRule extends Model
{
    protected $fillable = [
        'user_id', 'type', 'value', 'action', 'score_modifier', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'score_modifier' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'whitelist_ip'     => 'IP Permitida',
            'whitelist_email'  => 'Email Permitido',
            'whitelist_domain' => 'Dominio Permitido',
            'blacklist_ip'     => 'IP Bloqueada',
            'blacklist_email'  => 'Email Bloqueado',
            'blacklist_domain' => 'Dominio Bloqueado',
            default            => $this->type,
        };
    }

    public function isWhitelist(): bool
    {
        return str_starts_with($this->type, 'whitelist_');
    }
}
