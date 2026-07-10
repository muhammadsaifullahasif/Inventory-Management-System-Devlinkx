<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EbayFinanceTransaction extends Model
{
    protected $fillable = [
        'sales_channel_id',
        'order_id',
        'ebay_transaction_id',
        'ebay_order_id',
        'transaction_type',
        'fee_category',
        'booking_entry',
        'amount',
        'total_fee_amount',
        'currency',
        'payout_id',
        'transaction_date',
        'raw_payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'total_fee_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function salesChannel()
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
