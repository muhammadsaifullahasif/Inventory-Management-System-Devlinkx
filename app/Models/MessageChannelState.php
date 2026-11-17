<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-store messaging state: sync cursor, health and per-store switches.
 */
class MessageChannelState extends Model
{
    protected $fillable = [
        'sales_channel_id',
        'messaging_enabled',
        'auto_reply_enabled',
        'cursor',
        'last_synced_at',
        'last_error',
        'last_error_at',
    ];

    protected $casts = [
        'messaging_enabled' => 'boolean',
        'auto_reply_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    /**
     * The state row for a channel, created on first use.
     */
    public static function forChannel(SalesChannel $channel): self
    {
        return static::firstOrCreate(['sales_channel_id' => $channel->id]);
    }

    public function markSynced(?string $cursor = null): void
    {
        $this->forceFill([
            'cursor' => $cursor ?? $this->cursor,
            'last_synced_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'last_error' => mb_substr($error, 0, 2000),
            'last_error_at' => now(),
        ])->save();
    }
}
