<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeployPipelineRun extends Model
{
    protected $fillable = [
        'pipeline_id', 'status', 'output', 'triggered_by', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'output'      => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(DeployPipeline::class);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'success' => 'badge-success',
            'failed'  => 'badge-danger',
            'running' => 'badge-warning',
            'queued'  => 'badge-muted',
            default   => 'badge-muted',
        };
    }

    public function totalDurationMs(): int
    {
        $output = $this->output ?? [];
        $total = 0;
        foreach ($output as $stage) {
            $total += (int) ($stage['duration_ms'] ?? 0);
        }
        return $total;
    }
}