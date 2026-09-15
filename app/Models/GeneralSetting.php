<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Generic key-value settings store (meta_key/meta_value rows) — add new
 * settings later just by reading/writing a new key, no migration needed.
 * GeneralSetting::current() returns an object of all known keys (stored
 * values merged over defaults()), so callers keep using ->app_name etc.
 */
class GeneralSetting extends Model
{
    protected $fillable = [
        'meta_key',
        'meta_value',
    ];

    public static function get(string $key, $default = null)
    {
        try {
            return static::query()->where('meta_key', $key)->value('meta_value') ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function set(string $key, $value): void
    {
        static::query()->updateOrCreate(['meta_key' => $key], ['meta_value' => $value]);
    }

    public static function current(): object
    {
        $defaults = static::defaults();

        try {
            $stored = static::query()
                ->whereIn('meta_key', array_keys($defaults))
                ->pluck('meta_value', 'meta_key')
                ->toArray();
        } catch (\Throwable $e) {
            $stored = [];
        }

        return (object) array_merge($defaults, $stored);
    }

    protected static function defaults(): array
    {
        return [
            'app_name' => config('app.name', 'Sigma Body Parts'),
            'logo' => null,
            'admin_email' => null,
            'date_format' => 'Y-m-d',
            'week_start_day' => 0,
        ];
    }
}
