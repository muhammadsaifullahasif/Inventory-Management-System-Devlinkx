<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'sales_channel_id',
        'source',
        'ebay_return_id',
        'status',
        'reason',
        'buyer_comments',
        'notes',
        'refund_amount',
        'requested_at',
        'approved_at',
        'received_at',
        'refunded_at',
        'closed_at',
        'created_by',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'refunded_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function salesChannel()
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function items()
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEbayReturn(): bool
    {
        return $this->source === 'ebay' && !empty($this->ebay_return_id);
    }

    public function isFullyRestocked(): bool
    {
        return $this->items->every(fn (OrderReturnItem $item) => $item->restocked);
    }
}
