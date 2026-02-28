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
        'ebay_extended_order_id',
        'buyer_username',
        'buyer_email',
        'buyer_name',
        'buyer_first_name',
        'buyer_last_name',
        'buyer_phone',
        'shipping_name',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'shipping_country_name',
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
        'shipping_id',
        'tracking_number',
        'tracking_url',
        'shipping_label_path',
        'label_generated_at',
        'shipped_at',
        'delivered_at',
        'tracking_last_checked_at',
        'ebay_order_status',
        'ebay_payment_status',
        'cancel_status',
        'buyer_checkout_message',
        'ebay_raw_data',
        'notification_type',
        'notification_received_at',
        'order_date',
        'paid_at',
        'address_type',
        'address_validated_at',
        // Return tracking fields
        'return_status',
        'return_id',
        'return_reason',
        'return_requested_at',
        'return_closed_at',
        // Refund tracking fields
        'refund_status',
        'refund_amount',
        'total_refunded',
        'refund_initiated_at',
        'refund_completed_at',
        // Cancellation tracking fields
        'cancellation_id',
        'cancellation_reason',
        'cancellation_initiated_by',
        'cancellation_requested_at',
        'cancellation_closed_at',
    ];

    protected $casts = [
        'ebay_raw_data' => 'array',
        'order_date' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'tracking_last_checked_at' => 'datetime',
        'label_generated_at' => 'datetime',
        'notification_received_at' => 'datetime',
        'address_validated_at' => 'datetime',
        'return_requested_at' => 'datetime',
        'return_closed_at' => 'datetime',
        'refund_initiated_at' => 'datetime',
        'refund_completed_at' => 'datetime',
        'cancellation_requested_at' => 'datetime',
        'cancellation_closed_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'total_refunded' => 'decimal:2',
    ];

    /**
     * Get the sales channel for this order
     */
    public function salesChannel()
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    /**
     * Get the shipping carrier used for this order
     */
    public function shippingCarrier()
    {
        return $this->belongsTo(Shipping::class, 'shipping_id');
    }

    /**
     * Get the order items
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the order meta data
     */
    public function metas()
    {
        return $this->hasMany(OrderMeta::class);
    }

    /**
     * Get a specific meta value
     */
    public function getMeta(string $key, $default = null)
    {
        $meta = $this->metas()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : $default;
    }

    /**
     * Get a specific meta value as array (JSON decoded)
     */
    public function getMetaArray(string $key, $default = null): ?array
    {
        $value = $this->getMeta($key);
        if ($value === null) {
            return $default;
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    /**
     * Set a meta value
     */
    public function setMeta(string $key, $value): OrderMeta
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        return $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $value]
        );
    }

    /**
     * Delete a meta value
     */
    public function deleteMeta(string $key): bool
    {
        return $this->metas()->where('meta_key', $key)->delete() > 0;
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
     * Check if order has an active return request
     */
    public function hasActiveReturn(): bool
    {
        return !empty($this->return_status) &&
            !in_array($this->return_status, ['closed', 'cancelled', 'completed']);
    }

    /**
     * Check if order has a pending cancellation
     */
    public function hasPendingCancellation(): bool
    {
        return !empty($this->cancel_status) &&
            in_array(strtolower($this->cancel_status), ['cancelrequested', 'cancelpending', 'cancellation_requested']);
    }

    /**
     * Check if order is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->order_status === 'cancelled' ||
            (!empty($this->cancel_status) && in_array(strtolower($this->cancel_status), ['cancelled', 'cancelcomplete', 'cancelcompleted']));
    }

    /**
     * Check if order is refunded
     */
    public function isRefunded(): bool
    {
        return $this->payment_status === 'refunded' ||
            $this->refund_status === 'completed';
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return !$this->isCancelled() &&
            !$this->isRefunded() &&
            $this->fulfillment_status !== 'fulfilled' &&
            $this->order_status !== 'shipped' &&
            $this->order_status !== 'delivered';
    }

    /**
     * Check if order can be refunded
     */
    public function canBeRefunded(): bool
    {
        return !$this->isRefunded() &&
            $this->payment_status === 'paid';
    }

    /**
     * Check if order is partially refunded
     */
    public function isPartiallyRefunded(): bool
    {
        return $this->refund_status === 'partial' ||
            ($this->total_refunded > 0 && $this->total_refunded < $this->total);
    }

    /**
     * Get remaining refundable amount
     */
    public function getRefundableAmount(): float
    {
        return max(0, (float) $this->total - (float) ($this->total_refunded ?? 0));
    }

    /**
     * Check if order is a local sale (non-eBay)
     */
    public function isLocalSale(): bool
    {
        return empty($this->ebay_order_id);
    }

    /**
     * Record a partial refund
     */
    public function recordPartialRefund(float $amount, ?string $refundId = null): void
    {
        $newTotalRefunded = (float) ($this->total_refunded ?? 0) + $amount;

        $updateData = [
            'total_refunded' => $newTotalRefunded,
            'refund_initiated_at' => $this->refund_initiated_at ?? now(),
        ];

        // Check if fully refunded
        if ($newTotalRefunded >= $this->total) {
            $updateData['refund_status'] = 'completed';
            $updateData['refund_completed_at'] = now();
            $updateData['payment_status'] = 'refunded';
        } else {
            $updateData['refund_status'] = 'partial';
        }

        $this->update($updateData);

        // Log the refund
        $this->setMeta('refund_log_' . time(), [
            'amount' => $amount,
            'refund_id' => $refundId,
            'total_refunded' => $newTotalRefunded,
            'timestamp' => now()->toIso8601String(),
        ]);
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
     * Scope for orders with active returns
     */
    public function scopeWithActiveReturns($query)
    {
        return $query->whereNotNull('return_status')
            ->whereNotIn('return_status', ['closed', 'cancelled', 'completed']);
    }

    /**
     * Scope for orders with pending cancellations
     */
    public function scopeWithPendingCancellations($query)
    {
        return $query->whereNotNull('cancel_status')
            ->whereIn('cancel_status', ['CancelRequested', 'CancelPending', 'cancellation_requested']);
    }

    /**
     * Scope for cancelled orders
     */
    public function scopeCancelled($query)
    {
        return $query->where('order_status', 'cancelled');
    }

    /**
     * Scope for refunded orders
     */
    public function scopeRefunded($query)
    {
        return $query->where(function ($q) {
            $q->where('payment_status', 'refunded')
                ->orWhere('refund_status', 'completed');
        });
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
