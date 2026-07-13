<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainPerformanceSetting extends Model
{
    protected $fillable = [
        'domain_id',
        // 10.1
        'under_attack_mode', 'attack_rate', 'attack_burst', 'attack_conn',
        // 10.2
        'microcache_enabled', 'microcache_ttl', 'microcache_purged_at',
        // 10.3
        'geo_waf_enabled', 'geo_waf_mode', 'geo_waf_countries',
        // 10.4
        'orange_cloud', 'proxy_target', 'proxy_ssl_verify', 'proxy_timeout', 'proxy_websocket',
        // 10.5
        'goaccess_generated_at', 'goaccess_report_path',
        // 10.6
        'hsts_enabled', 'hsts_max_age', 'hsts_include_subdomains', 'hsts_preload',
        'custom_headers', 'redirects', 'brotli_enabled',
    ];

    protected $casts = [
        // booleans
        'under_attack_mode'       => 'boolean',
        'microcache_enabled'      => 'boolean',
        'geo_waf_enabled'         => 'boolean',
        'orange_cloud'            => 'boolean',
        'proxy_ssl_verify'        => 'boolean',
        'proxy_websocket'         => 'boolean',
        'hsts_enabled'            => 'boolean',
        'hsts_include_subdomains' => 'boolean',
        'hsts_preload'            => 'boolean',
        'brotli_enabled'          => 'boolean',
        // json
        'geo_waf_countries' => 'array',
        'custom_headers'    => 'array',
        'redirects'         => 'array',
        // dates
        'microcache_purged_at'   => 'datetime',
        'goaccess_generated_at'  => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Build the HSTS header value string.
     */
    public function hstsHeaderValue(): string
    {
        $parts = ["max-age={$this->hsts_max_age}"];
        if ($this->hsts_include_subdomains) $parts[] = 'includeSubDomains';
        if ($this->hsts_preload)            $parts[] = 'preload';
        return implode('; ', $parts);
    }
}
