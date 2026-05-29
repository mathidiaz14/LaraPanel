<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'domain_id', 'username', 'email', 'password_hash',
        'quota_bytes', 'used_bytes', 'is_active', 'can_send', 'can_receive',
        'forwarders',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_send' => 'boolean',
        'can_receive' => 'boolean',
        'forwarders' => 'array',
        'quota_bytes' => 'integer',
        'used_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function quotaFormatted(): string
    {
        $bytes = $this->quota_bytes;
        if ($bytes <= 0) return 'Ilimitado';
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 0) . ' ' . $units[$i];
    }

    public function usedFormatted(): string
    {
        $bytes = $this->used_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
