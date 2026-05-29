<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CronRunLog extends Model
{
    protected $fillable = [
        'cron_job_id', 'status', 'output', 'exit_code', 'duration_ms', 'ran_at',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
        'exit_code' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function cronJob(): BelongsTo
    {
        return $this->belongsTo(CronJob::class);
    }

    public function durationFormatted(): string
    {
        if ($this->duration_ms < 1000) return $this->duration_ms . 'ms';
        return round($this->duration_ms / 1000, 1) . 's';
    }
}
