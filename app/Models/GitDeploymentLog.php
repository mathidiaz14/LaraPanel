<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GitDeploymentLog extends Model
{
    protected $fillable = [
        'git_deployment_id', 'commit_hash', 'commit_message', 
        'status', 'output', 'triggered_by'
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
}
