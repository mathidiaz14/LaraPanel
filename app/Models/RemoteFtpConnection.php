<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemoteFtpConnection extends Model
{
    protected $fillable = [
        'user_id', 'name', 'host', 'port', 'protocol',
        'username', 'password', 'passive', 'initial_path',
        'last_connected_at',
    ];

    protected $casts = [
        'password'          => 'encrypted',
        'port'              => 'integer',
        'passive'           => 'boolean',
        'last_connected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}