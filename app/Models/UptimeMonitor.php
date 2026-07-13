<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UptimeMonitor extends Model
{
    protected $fillable = [
        'user_id',
        'server_id',
        'name',
        'type',
        'target',
        'interval_minutes',
        'status',
        'last_checked_at',
        'last_error'
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pings()
    {
        return $this->hasMany(UptimePing::class);
    }
}
