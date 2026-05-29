<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'two_factor_enabled',
        'avatar',
        'timezone',
        'language',
        'plan_id',
        'last_login_at',
        'last_login_ip',
        'suspended_at',
        'suspension_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'is_active'            => 'boolean',
            'two_factor_enabled'   => 'boolean',
            'last_login_at'        => 'datetime',
            'suspended_at'         => 'datetime',
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ────────────────────────────────────────────────────────────────
    // Role Helpers
    // ────────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isReseller(): bool
    {
        return $this->role === 'reseller';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    // ────────────────────────────────────────────────────────────────
    // Plan Helpers
    // ────────────────────────────────────────────────────────────────

    public function canAddDomain(): bool
    {
        if ($this->isAdmin()) return true;
        if (!$this->plan) return false;
        return $this->domains()->active()->count() < $this->plan->max_domains;
    }

    public function domainQuotaUsed(): int
    {
        return $this->domains()->active()->count();
    }

    public function getDbPrefix(): string
    {
        $part = explode('@', $this->email)[0];
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $part);
        return strtolower(substr($clean, 0, 8)) . '_';
    }
}
