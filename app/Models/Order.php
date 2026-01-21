<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'sales_channel_id',
        'ebay_order_id',
        'buyer_username',
        'buyer_email',
        'buyer_name',
        'buyer_phone',
        'shipping_name',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'subtotal',
        'shipping_cost',
        'tax',
        'discount',
        'total',
        'currency',
        'order_status',
        'payment_status',
        'fulfillment_status',
        'shipping_carrier',
        'tracking_number',
        'shipped_at',
        'ebay_order_status',
        'ebay_payment_status',
        'ebay_raw_data',
        'order_date',
        'paid_at',
    ];

    protected $casts = [
        'ebay_raw_data' => 'array',
        'order_date' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Get the sales channel for this order
     */
    public function salesChannel()
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    /**
     * Get the order items
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get full shipping address as string
     */
    public function getFullShippingAddressAttribute(): string
    {
        $parts = array_filter([
            $this->shipping_name,
            $this->shipping_address_line1,
            $this->shipping_address_line2,
            $this->shipping_city,
            $this->shipping_state . ' ' . $this->shipping_postal_code,
            $this->shipping_country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if order is from eBay
     */
    public function isEbayOrder(): bool
    {
        return !empty($this->ebay_order_id);
    }

    /**
     * Scope for pending orders
     */
    public function scopePending($query)
    {
        return $query->where('order_status', 'pending');
    }

    /**
     * Scope for paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope for unfulfilled orders
     */
    public function scopeUnfulfilled($query)
    {
        return $query->where('fulfillment_status', 'unfulfilled');
    }

    /**
     * Scope for orders from a specific sales channel
     */
    public function scopeFromChannel($query, $channelId)
    {
        return $query->where('sales_channel_id', $channelId);
    }

    /**
     * Generate a unique order number
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return "{$prefix}-{$date}-{$random}";
    }
}
