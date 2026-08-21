<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    protected $fillable = [
        'name',
        'url',
        'check_interval_minutes',
        'timeout_seconds',
        'check_ssl',
        'is_active',
        'uptime_status',
        'uptime_last_checked_at',
        'uptime_status_changed_at',
        'uptime_check_response_time_ms',
        'uptime_check_failure_reason',
        'ssl_status',
        'ssl_expiration_date',
        'ssl_check_failure_reason',
    ];

    protected $casts = [
        'check_ssl' => 'boolean',
        'is_active' => 'boolean',
        'uptime_last_checked_at' => 'datetime',
        'uptime_status_changed_at' => 'datetime',
        'ssl_expiration_date' => 'date',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(MonitorHistory::class)->latest('created_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Monitors that are due for a check: never checked yet, or the
     * per-monitor interval has elapsed since the last check.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('uptime_last_checked_at')
                ->orWhereRaw('TIMESTAMPDIFF(MINUTE, uptime_last_checked_at, NOW()) >= check_interval_minutes');
        });
    }
}
