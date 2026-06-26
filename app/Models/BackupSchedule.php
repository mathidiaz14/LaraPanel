<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain_id',
        'type',
        'disk',
        'frequency',
        'retention_count',
        'is_active',
        'next_run_at',
        'last_run_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'retention_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
