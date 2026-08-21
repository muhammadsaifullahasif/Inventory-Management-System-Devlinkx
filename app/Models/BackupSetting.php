<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings table. Read via BackupSetting::current() everywhere —
 * it degrades to sane defaults (matching config/backup.php) if the row or
 * even the table doesn't exist yet (fresh install, migration not yet run).
 */
class BackupSetting extends Model
{
    protected $fillable = [
        'schedule_enabled',
        'notification_email',
        'keep_daily_backups_for_days',
        'keep_weekly_backups_for_weeks',
        'keep_monthly_backups_for_months',
    ];

    protected $casts = [
        'schedule_enabled' => 'boolean',
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
            'schedule_enabled' => true,
            'notification_email' => null,
            'keep_daily_backups_for_days' => 7,
            'keep_weekly_backups_for_weeks' => 4,
            'keep_monthly_backups_for_months' => 3,
        ]);
    }
}
