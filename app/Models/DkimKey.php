<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DkimKey extends Model
{
    protected $fillable = [
        'domain_id', 'selector', 'private_key_path', 'public_key',
        'dns_value', 'key_size', 'algorithm', 'is_active', 'deployed_at',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'key_size'    => 'integer',
        'deployed_at' => 'datetime',
    ];

    // Never expose the private key path in serialization
    protected $hidden = ['private_key_path'];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Full DKIM DNS record name: mail._domainkey.example.com
     */
    public function dnsRecordName(): string
    {
        return $this->selector . '._domainkey.' . $this->domain?->name;
    }

    /**
     * Format the TXT record content for DNS zone.
     */
    public function dnsRecordContent(): string
    {
        return '"v=DKIM1; k=' . $this->algorithm . '; p=' . $this->public_key . '"';
    }
}
