<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Backup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'domain_id', 'label', 'type', 'status',
        'filename', 'size_bytes', 'notes', 'error_message',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
        if ($bytes <= 0) return '—';
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function durationFormatted(): string
    {
        if (!$this->started_at || !$this->completed_at) return '—';
        $seconds = $this->started_at->diffInSeconds($this->completed_at);
        if ($seconds < 60) return "{$seconds}s";
        $minutes = intdiv($seconds, 60);
        $sec = $seconds % 60;
        return "{$minutes}m {$sec}s";
    }
}
