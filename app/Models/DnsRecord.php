<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsRecord extends Model
{
    protected $fillable = [
        'dns_zone_id', 'name', 'type', 'content', 'ttl',
        'priority', 'is_disabled', 'pdns_record_id', 'comment',
    ];

    protected $casts = [
        'ttl'         => 'integer',
        'priority'    => 'integer',
        'is_disabled' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DnsZone::class, 'dns_zone_id');
    }

    /**
     * Return the display name (@ if apex record).
     */
    public function displayName(): string
    {
        $zone = $this->zone?->name ?? '';
        if ($this->name === $zone || $this->name === rtrim($zone, '.') || $this->name === '@') {
            return '@';
        }
        return $this->name;
    }

    /**
     * Color badge class by record type.
     */
    public function typeBadgeClass(): string
    {
        return match($this->type) {
            'A', 'AAAA' => 'badge-accent',
            'MX'        => 'badge-success',
            'TXT'       => 'badge-warning',
            'CNAME'     => 'badge-muted',
            'NS'        => 'badge-muted',
            'SRV'       => 'badge-danger',
            default     => 'badge-muted',
        };
    }
}
