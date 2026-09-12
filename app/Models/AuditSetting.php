<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings table. Read via AuditSetting::current() everywhere —
 * it degrades to sane defaults if the row or even the table doesn't exist
 * yet (fresh install, migration not yet run). Mirrors BackupSetting.
 */
class AuditSetting extends Model
{
    protected $fillable = [
        'archive_enabled',
        'retention_days',
    ];

    protected $casts = [
        'archive_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        try {
            return static::query()->first() ?? static::defaults();
        } catch (\Throwable $e) {
            return static::defaults();
        }
    }

    protected static function defaults(): self
    {
        return new static([
            'archive_enabled' => true,
            'retention_days' => 90,
        ]);
    }
}
