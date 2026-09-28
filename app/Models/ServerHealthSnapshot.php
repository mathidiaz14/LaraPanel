<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerHealthSnapshot extends Model
{
    protected $table = 'server_health_snapshots';

    const UPDATED_AT = null;

    protected $fillable = [
        'server_id',
        'score',
        'grade',
        'components',
    ];

    protected $casts = [
        'server_id'  => 'integer',
        'score'      => 'integer',
        'components' => 'array',
        'created_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeRecent($query, int $limit = 30)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }
}