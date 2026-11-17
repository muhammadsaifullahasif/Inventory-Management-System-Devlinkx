<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';
    public const DIRECTION_INTERNAL_NOTE = 'internal_note';

    public const SENDER_BUYER = 'buyer';
    public const SENDER_SELLER = 'seller';
    public const SENDER_PLATFORM = 'platform';
    public const SENDER_AUTO_REPLY = 'auto_reply';
    public const SENDER_TEAM = 'team';

    public const DELIVERY_SENT = 'sent';
    public const DELIVERY_FAILED = 'failed';

    protected $fillable = [
        'thread_id',
        'external_message_id',
        'direction',
        'sender_kind',
        'sender_name',
        'sent_by_user_id',
        'body',
        'attachments',
        'delivery_status',
        'error',
        'is_auto_reply',
        'sent_at',
        'read_at',
        'raw',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_auto_reply' => 'boolean',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'raw' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    public function isInternalNote(): bool
    {
        return $this->direction === self::DIRECTION_INTERNAL_NOTE;
    }
}
