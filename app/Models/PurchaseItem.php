<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'barcode',
        'sku',
        'name',
        'quantity',
        'received_quantity',
        'received_at',
        'previous_quantity',
        'avg_cost',
        'price',
        'note',
        'rack_id',
        'active_status',
        'delete_status',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'received_at'       => 'datetime',
        'previous_quantity' => 'decimal:4',
        'avg_cost'          => 'decimal:4',
        'price'             => 'decimal:2',
    ];

    /**
     * Get the purchase that owns this item.
     */
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get the product for this item.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the rack for this item.
     */
    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Check if item is fully received.
     */
    public function isFullyReceived(): bool
    {
        return (float) $this->received_quantity >= (float) $this->quantity;
    }

    /**
     * Check if item is partially received.
     */
    public function isPartiallyReceived(): bool
    {
        return (float) $this->received_quantity > 0 && (float) $this->received_quantity < (float) $this->quantity;
    }

    /**
     * Get pending quantity to receive.
     */
    public function getPendingQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_quantity);
    }
}
