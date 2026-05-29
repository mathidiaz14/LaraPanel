<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    protected $fillable = [
        'cpu_usage', 'ram_usage', 'ram_total', 'ram_used',
        'disk_usage', 'disk_total', 'disk_used',
        'net_in', 'net_out', 'load_1', 'load_5', 'load_15',
        'process_count', 'services', 'recorded_at',
    ];

    protected $casts = [
        'services'    => 'array',
        'recorded_at' => 'datetime',
    ];

    public $timestamps = false;

    public function scopeRecent($query, int $hours = 1)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours))
                     ->orderBy('recorded_at');
    }

    /**
     * Get human-readable RAM usage string.
     */
    public function ramDisplay(): string
    {
        return sprintf('%s / %s', $this->bytesToHuman($this->ram_used), $this->bytesToHuman($this->ram_total));
    }

    private function bytesToHuman(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
