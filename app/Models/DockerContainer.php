<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DockerContainer extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'image',
        'container_id',
        'domain',
        'compose_stack',
        'compose_file',
        'ports',
        'env_vars',
        'volumes',
        'notes',
        'last_status',
    ];

    protected $casts = [
        'ports'    => 'array',
        'env_vars' => 'array',
        'volumes'  => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return $this->last_status === 'running';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->last_status) {
            'running'    => 'badge-success',
            'paused'     => 'badge-warning',
            'restarting' => 'badge-info',
            default      => 'badge-secondary',
        };
    }
}
