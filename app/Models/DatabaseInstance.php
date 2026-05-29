<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DatabaseInstance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'domain_id', 'db_name', 'display_name', 'db_user',
        'db_password_hint', 'db_host', 'db_port', 'engine',
        'size_bytes', 'is_active', 'last_backup_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_backup_at' => 'datetime',
        'size_bytes' => 'integer',
        'db_port' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function sizeFormatted(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
