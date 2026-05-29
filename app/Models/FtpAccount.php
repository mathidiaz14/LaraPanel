<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FtpAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'domain_id', 'username', 'password_hash',
        'home_directory', 'quota_bytes', 'is_active', 'readonly',
        'last_login_at', 'last_login_ip',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'readonly' => 'boolean',
        'quota_bytes' => 'integer',
        'last_login_at' => 'datetime',
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
}
