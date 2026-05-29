<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailAlias extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'domain_id', 'source', 'destinations',
        'is_catchall', 'is_active', 'notes',
    ];

    protected $casts = [
        'destinations' => 'array',
        'is_catchall'  => 'boolean',
        'is_active'    => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function destinationsFormatted(): string
    {
        return implode(', ', $this->destinations ?? []);
    }

    public function isCatchAll(): bool
    {
        return $this->is_catchall || str_starts_with($this->source, '@');
    }
}
