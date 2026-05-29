<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GitDeployment extends Model
{
    protected $fillable = [
        'user_id', 'domain_name', 'repository_url', 'branch',
        'deploy_script', 'webhook_secret', 'webhook_id', 'auto_deploy',
        'last_deployed_at',
    ];

    protected $casts = [
        'auto_deploy'      => 'boolean',
        'last_deployed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->webhook_id)) {
                $model->webhook_id = Str::uuid()->toString();
            }
            if (empty($model->webhook_secret)) {
                $model->webhook_secret = Str::random(32);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GitDeploymentLog::class)->orderByDesc('created_at');
    }

    public function getWebhookUrlAttribute(): string
    {
        return url("/api/webhooks/git/{$this->webhook_id}");
    }
}
