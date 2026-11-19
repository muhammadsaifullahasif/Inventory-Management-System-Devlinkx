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
        'needs_reauthorization',
        'cursor',
        'last_synced_at',
        'last_error',
        'last_error_at',
    ];

    protected $casts = [
        'messaging_enabled' => 'boolean',
        'auto_reply_enabled' => 'boolean',
        'needs_reauthorization' => 'boolean',
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
        // Defaults are passed explicitly: a freshly created model does not carry the column
        // defaults, so messaging_enabled would read as empty (= disabled) on the first call.
        return static::firstOrCreate(
            ['sales_channel_id' => $channel->id],
            ['messaging_enabled' => true, 'auto_reply_enabled' => false],
        );
    }

    public function markSynced(?string $cursor = null): void
    {
        $this->forceFill([
            'cursor' => $cursor ?? $this->cursor,
            'last_synced_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
            'needs_reauthorization' => false,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'last_error' => mb_substr($error, 0, 2000),
            'last_error_at' => now(),
        ])->save();
    }

    /**
     * The platform refused because the store has not granted (or lost) the messaging permission.
     * Only a successful sync clears this, i.e. after the store has been re-authorized.
     *
     * @return bool true when this call newly raised the flag (so admins are told once, not every 2 minutes)
     */
    public function markNeedsReauthorization(string $error): bool
    {
        $wasRaised = (bool) $this->needs_reauthorization;
        $this->markFailed($error);
        $this->forceFill(['needs_reauthorization' => true])->save();

        return !$wasRaised;
    }
}
