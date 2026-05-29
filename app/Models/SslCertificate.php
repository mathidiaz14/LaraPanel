<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SslCertificate extends Model
{
    protected $fillable = [
        'domain_id', 'provider', 'status', 'certificate', 'private_key',
        'chain', 'issued_at', 'expires_at', 'auto_renew', 'last_renewed_at',
        'last_error', 'san_domains',
    ];

    protected $casts = [
        'issued_at'       => 'datetime',
        'expires_at'      => 'datetime',
        'last_renewed_at' => 'datetime',
        'auto_renew'      => 'boolean',
        'san_domains'     => 'array',
    ];

    // Never expose private_key in JSON serialization
    protected $hidden = ['private_key'];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Decrypt and return the private key (use sparingly — only for deployment).
     */
    public function decryptedPrivateKey(): ?string
    {
        if (!$this->private_key || str_starts_with($this->private_key, '(dev-mode')) {
            return $this->private_key;
        }
        try {
            return Crypt::decryptString($this->private_key);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->expires_at) return null;
        return (int) now()->diffInDays($this->expires_at, absolute: false);
    }

    public function isExpiringSoon(int $thresholdDays = 14): bool
    {
        $days = $this->daysUntilExpiry();
        return $days !== null && $days <= $thresholdDays && $days >= 0;
    }

    public function providerLabel(): string
    {
        return match($this->provider) {
            'letsencrypt' => "Let's Encrypt",
            'custom'      => 'Certificado Personalizado',
            'selfsigned'  => 'Auto-firmado',
            default       => $this->provider,
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'active'     => 'Activo',
            'pending'    => 'Emitiendo...',
            'failed'     => 'Error',
            'expired'    => 'Expirado',
            'revoked'    => 'Revocado',
            default      => $this->status,
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
                     ->where('expires_at', '>', now());
    }
}
