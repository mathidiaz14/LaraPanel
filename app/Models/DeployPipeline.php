<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeployPipeline extends Model
{
    protected $fillable = [
        'user_id', 'git_deployment_id', 'name', 'stages', 'branch',
        'last_run_status', 'last_run_at', 'enable_webhook',
    ];

    protected $casts = [
        'stages'          => 'array',
        'enable_webhook'  => 'boolean',
        'last_run_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gitDeployment(): BelongsTo
    {
        return $this->belongsTo(GitDeployment::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(DeployPipelineRun::class)->orderByDesc('created_at');
    }

    public function stageCount(): int
    {
        return count($this->stages ?? []);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->last_run_status) {
            'success' => 'badge-success',
            'failed'  => 'badge-danger',
            'running' => 'badge-warning',
            'queued'  => 'badge-muted',
            default   => 'badge-muted',
        };
    }
}