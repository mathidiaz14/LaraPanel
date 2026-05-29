<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CronJob extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'label', 'command', 'schedule', 'user',
        'is_active', 'last_run_at', 'last_run_status',
        'last_run_output', 'run_count', 'fail_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'run_count' => 'integer',
        'fail_count' => 'integer',
    ];

    public function userRelation(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
