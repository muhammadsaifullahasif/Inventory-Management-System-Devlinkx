<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings table. Read via ShippingSetting::current() everywhere —
 * it degrades to sane defaults if the row or even the table doesn't exist yet
 * (fresh install, migration not yet run).
 */
class ShippingSetting extends Model
{
    protected $fillable = [
        'cutoff_hour',
    ];

    protected $casts = [
        'cutoff_hour' => 'integer',
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
            'cutoff_hour' => 14,
        ]);
    }
}
