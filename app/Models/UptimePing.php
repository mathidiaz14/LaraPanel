<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UptimePing extends Model
{
    protected $fillable = [
        'uptime_monitor_id',
        'status',
        'response_time_ms',
    ];

    public function monitor()
    {
        return $this->belongsTo(UptimeMonitor::class, 'uptime_monitor_id');
    }
}
