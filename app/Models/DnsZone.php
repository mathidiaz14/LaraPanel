<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DnsZone extends Model
{
    protected $fillable = [
        'user_id', 'domain_id', 'name', 'pdns_zone_id', 'type', 'is_active',
        'serial', 'primary_ns', 'secondary_ns', 'admin_email', 'ttl_default',
        'refresh', 'retry', 'expire', 'minimum_ttl', 'notes',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'serial'      => 'integer',
        'ttl_default' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }

    public function fqdn(): string
    {
        // Ensure trailing dot for DNS FQDN
        return rtrim($this->name, '.') . '.';
    }

    public function recordsCountByType(): array
    {
        return $this->records()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();
    }
}
