<?php

namespace App\Models;

use App\Models\SslCertificate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domain extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'type', 'parent_domain', 'document_root',
        'php_version', 'webserver', 'ssl_enabled', 'ssl_expires_at',
        'ssl_provider', 'is_active', 'status', 'config', 'deployed_at',
        'error_pages', 'last_ssl_alerted_at',
    ];

    protected $casts = [
        'ssl_enabled'   => 'boolean',
        'is_active'     => 'boolean',
        'ssl_expires_at'=> 'datetime',
        'last_ssl_alerted_at' => 'datetime',
        'deployed_at'   => 'datetime',
        'config'        => 'array',
        'error_pages'   => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sslCertificate(): HasOne
    {
        return $this->hasOne(SslCertificate::class);
    }

    public function performanceSetting(): HasOne
    {
        return $this->hasOne(DomainPerformanceSetting::class);
    }

    /**
     * Get (or create) the performance settings record.
     */
    public function getPerformance(): DomainPerformanceSetting
    {
        return $this->performanceSetting ?? $this->performanceSetting()->create(['domain_id' => $this->id]);
    }

    public function isMain(): bool
    {
        return $this->type === 'main';
    }

    public function isSubdomain(): bool
    {
        return $this->type === 'subdomain';
    }

    public function isProxy(): bool
    {
        return $this->type === 'proxy';
    }

    public function getProxyPort(): ?int
    {
        return $this->config['proxy_port'] ?? null;
    }

    /**
     * List of upstream backend addresses for staging / load-balancing groups.
     * Stored inside the `config` JSON column to avoid extra migrations.
     */
    public function getStagingUpstreams(): array
    {
        $upstreams = $this->config['staging_upstreams'] ?? [];
        return is_array($upstreams) ? array_values(array_filter($upstreams)) : [];
    }

    public function usesStagingUpstream(): bool
    {
        return $this->getStagingUpstreams() !== [];
    }

    /**
     * Whether the domain is currently serving the staging upstream group.
     */
    public function onStaging(): bool
    {
        return (bool) ($this->config['on_staging'] ?? false);
    }

    public function sslExpiresInDays(): ?int
    {
        if (!$this->ssl_expires_at) return null;
        return (int) now()->diffInDays($this->ssl_expires_at, absolute: false);
    }

    public function sslIsExpiringSoon(): bool
    {
        $days = $this->sslExpiresInDays();
        return $days !== null && $days <= 14;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
