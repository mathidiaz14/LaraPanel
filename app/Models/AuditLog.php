<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'subject', 'subject_type', 'subject_id',
        'meta', 'ip_address', 'user_agent', 'severity',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Record an audit log entry from anywhere in the app.
     */
    public static function record(
        string  $action,
        string  $subject = '',
        array   $meta = [],
        string  $severity = 'info',
        ?int    $userId = null,
    ): static {
        return static::create([
            'user_id'    => $userId ?? auth()->id(),
            'action'     => $action,
            'subject'    => $subject,
            'meta'       => $meta,
            'severity'   => $severity,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAction($query, string $action)
    {
        return $query->where('action', 'like', $action . '%');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }
}
