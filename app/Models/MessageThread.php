<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A buyer conversation on any sales channel. Channel-agnostic: nothing here
 * knows about eBay; platform specifics live in the channel's MessageChannelDriver.
 */
class MessageThread extends Model
{
    public const KIND_PRE_PURCHASE = 'pre_purchase';
    public const KIND_POST_PURCHASE = 'post_purchase';
    public const KIND_PLATFORM_SYSTEM = 'platform_system';
    public const KIND_UNKNOWN = 'unknown';

    public const STATUS_PENDING = 'pending';               // needs a team reply
    public const STATUS_AWAITING_BUYER = 'awaiting_buyer';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'sales_channel_id',
        'external_thread_id',
        'thread_kind',
        'buyer_username',
        'buyer_name',
        'buyer_external_id',
        'subject',
        'reference_type',
        'external_reference_id',
        'product_id',
        'order_id',
        'status',
        'assigned_user_id',
        'unread_count',
        'last_message_at',
        'last_message_preview',
        'last_message_direction',
        'auto_replied_at',
        'external_updated_at',
        'raw',
    ];

    protected $casts = [
        'unread_count' => 'integer',
        'last_message_at' => 'datetime',
        'auto_replied_at' => 'datetime',
        'external_updated_at' => 'datetime',
        'raw' => 'array',
    ];

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isPlatformSystem(): bool
    {
        return $this->thread_kind === self::KIND_PLATFORM_SYSTEM;
    }
}
