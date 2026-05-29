<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price', 'max_domains',
        'max_subdomains', 'max_email_accounts', 'max_databases',
        'max_ftp_accounts', 'disk_quota_bytes', 'bandwidth_bytes',
        'max_cron_jobs', 'ssl_enabled', 'backups_enabled',
        'terminal_enabled', 'is_active', 'features',
    ];

    protected $casts = [
        'ssl_enabled'      => 'boolean',
        'backups_enabled'  => 'boolean',
        'terminal_enabled' => 'boolean',
        'is_active'        => 'boolean',
        'features'         => 'array',
        'price'            => 'float',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function diskQuotaGb(): float
    {
        return round($this->disk_quota_bytes / 1073741824, 1);
    }

    public function bandwidthGb(): float
    {
        return round($this->bandwidth_bytes / 1073741824, 1);
    }
}
