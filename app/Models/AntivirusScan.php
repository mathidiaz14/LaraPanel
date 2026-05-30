<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AntivirusScan extends Model
{
    protected $fillable = [
        'user_id',
        'path',
        'files_scanned',
        'infected_count',
        'error_count',
        'duration_seconds',
        'status',
        'quarantine_enabled',
        'raw_output',
    ];

    protected $casts = [
        'quarantine_enabled' => 'boolean',
        'files_scanned'      => 'integer',
        'infected_count'     => 'integer',
        'error_count'        => 'integer',
        'duration_seconds'   => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quarantineFiles(): HasMany
    {
        return $this->hasMany(QuarantineFile::class, 'scan_id');
    }

    public function isClean(): bool
    {
        return $this->status === 'clean';
    }

    public function isInfected(): bool
    {
        return $this->status === 'infected';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'clean'    => 'badge-success',
            'infected' => 'badge-danger',
            'error'    => 'badge-warning',
            default    => 'badge-secondary',
        };
    }

    /**
     * Parse clamscan output to extract summary numbers.
     */
    public static function parseSummary(string $output): array
    {
        $scanned  = 0;
        $infected = 0;
        $errors   = 0;

        if (preg_match('/Scanned files:\s*(\d+)/i', $output, $m)) {
            $scanned = (int) $m[1];
        }
        if (preg_match('/Infected files:\s*(\d+)/i', $output, $m)) {
            $infected = (int) $m[1];
        }
        if (preg_match('/(?:Total errors|Errors):\s*(\d+)/i', $output, $m)) {
            $errors = (int) $m[1];
        }

        return compact('scanned', 'infected', 'errors');
    }
}
