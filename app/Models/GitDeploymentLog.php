<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GitDeploymentLog extends Model
{
    protected $fillable = [
        'git_deployment_id', 'commit_hash', 'commit_message', 'requested_commit',
        'status', 'output', 'triggered_by', 'duration_ms', 'finished_at'
    ];

    protected $casts = [
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
    ];

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(GitDeployment::class, 'git_deployment_id');
    }

    public function statusBadgeClass(): string
    {
        return match($this->status) {
            'success' => 'badge-success',
            'failed'  => 'badge-danger',
            'running' => 'badge-warning',
            default   => 'badge-muted',
        };
    }

    public function requestedCommit(): string
    {
        return $this->requested_commit ?: $this->commit_hash ?: '—';
    }

    public function durationLabel(): string
    {
        if (! $this->duration_ms) {
            return $this->finished_at ? '—' : 'En curso';
        }

        return $this->duration_ms >= 1000
            ? number_format($this->duration_ms / 1000, 1) . ' s'
            : $this->duration_ms . ' ms';
    }
}
