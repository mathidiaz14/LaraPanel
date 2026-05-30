<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarantineFile extends Model
{
    protected $fillable = [
        'user_id',
        'scan_id',
        'original_path',
        'quarantine_path',
        'threat_name',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(AntivirusScan::class, 'scan_id');
    }

    public function existsOnDisk(): bool
    {
        return file_exists($this->quarantine_path);
    }

    public function formattedSize(): string
    {
        $bytes = $this->file_size;
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }
}
