<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirewallRule extends Model
{
    protected $fillable = [
        'user_id', 'name', 'action', 'port', 'protocol',
        'source_ip', 'destination_ip', 'direction', 'sort_order',
        'is_active', 'is_preset', 'ufw_rule_id', 'notes',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_preset'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionBadgeClass(): string
    {
        return match($this->action) {
            'allow'  => 'badge-success',
            'deny'   => 'badge-danger',
            'reject' => 'badge-danger',
            'limit'  => 'badge-warning',
            default  => 'badge-muted',
        };
    }

    public function directionIcon(): string
    {
        return match($this->direction) {
            'in'  => 'fa-arrow-down',
            'out' => 'fa-arrow-up',
            default => 'fa-arrows-left-right',
        };
    }

    /**
     * Format the port/protocol for display.
     */
    public function portDisplay(): string
    {
        if (!$this->port) return 'Todo';
        $proto = $this->protocol === 'any' ? '' : '/' . strtoupper($this->protocol);
        return $this->port . $proto;
    }

    /**
     * Build the UFW command arguments for this rule.
     */
    public function toUfwArgs(): array
    {
        $args = [$this->action];

        if ($this->direction !== 'any') {
            $args[] = $this->direction;
        }

        if ($this->source_ip && $this->direction === 'in') {
            $args[] = 'from';
            $args[] = $this->source_ip;
        }

        if ($this->port) {
            $args[] = 'to';
            $args[] = 'any';
            $args[] = 'port';
            $args[] = $this->port;
        }

        if ($this->protocol !== 'any') {
            $args[] = 'proto';
            $args[] = $this->protocol;
        }

        return $args;
    }
}
