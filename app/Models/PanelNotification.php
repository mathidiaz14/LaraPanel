<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class PanelNotification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'action_url', 'data', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * @param  iterable<int, User>  $users
     * @param  array<string, mixed>  $attributes
     */
    public static function forUsers(iterable $users, array $attributes): void
    {
        $payload = [
            'type' => $attributes['type'] ?? null,
            'title' => $attributes['title'] ?? 'Notificación',
            'message' => $attributes['message'] ?? '',
            'action_url' => $attributes['action_url'] ?? null,
            'data' => $attributes['data'] ?? [],
        ];

        foreach ($users as $user) {
            static::create(array_merge($payload, ['user_id' => $user->id]));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function forAdmins(array $attributes): void
    {
        $admins = User::query()->where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            $admins = User::query()->limit(1)->get();
        }

        self::forUsers($admins, $attributes);
    }
}
