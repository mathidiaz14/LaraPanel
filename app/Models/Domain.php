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
    ];

    protected $casts = [
        'ssl_enabled'  => 'boolean',
        'is_active'    => 'boolean',
        'ssl_expires_at'=> 'datetime',
        'deployed_at'  => 'datetime',
        'config'       => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sslCertificate(): HasOne
    {
        return $this->hasOne(SslCertificate::class);
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
