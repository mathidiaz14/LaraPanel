<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalCommandHistory extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'terminal_command_history';

    protected $fillable = [
        'user_id', 'server_id', 'command', 'cwd', 'status', 'output',
        'exit_code', 'background', 'cancel_requested', 'started_at',
        'finished_at', 'duration_ms',
    ];

    protected $casts = [
        'background' => 'boolean',
        'cancel_requested' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['success', 'failed', 'cancelled'], true);
    }
}
