<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings table. Read via MessageSetting::current() — it degrades
 * to defaults if the row or the table doesn't exist yet (same approach as ShippingSetting).
 */
class MessageSetting extends Model
{
    protected $fillable = [
        'timezone',
        'office_hours',
        'auto_reply_enabled',
        'auto_reply_text',
        'auto_reply_cooldown_hours',
        'retention_days',
    ];

    protected $casts = [
        'office_hours' => 'array',
        'auto_reply_enabled' => 'boolean',
        'auto_reply_cooldown_hours' => 'integer',
        'retention_days' => 'integer',
    ];

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public static function current(): self
    {
        try {
            return static::query()->first() ?? static::defaults();
        } catch (\Throwable $e) {
            return static::defaults();
        }
    }

    public static function defaults(): self
    {
        $weekday = ['open' => '08:00', 'close' => '17:00'];

        return new static([
            'timezone' => config('app.timezone', 'UTC'),
            'office_hours' => [
                'mon' => $weekday,
                'tue' => $weekday,
                'wed' => $weekday,
                'thu' => $weekday,
                'fri' => $weekday,
                'sat' => $weekday,
                'sun' => null, // closed
            ],
            'auto_reply_enabled' => false,
            'auto_reply_text' => 'Thanks for your message. Our team is currently offline. We will reply during our next working day.',
            'auto_reply_cooldown_hours' => 12,
            'retention_days' => null,
        ]);
    }
}
