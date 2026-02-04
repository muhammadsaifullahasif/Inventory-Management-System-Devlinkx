<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'ebay_item_id',
        'ebay_transaction_id',
        'ebay_line_item_id',
        'sku',
        'title',
        'quantity',
        'unit_price',
        'total_price',
        'actual_shipping_cost',
        'actual_handling_cost',
        'final_value_fee',
        'currency',
        'listing_type',
        'condition_id',
        'condition_display_name',
        'site',
        'shipping_service',
        'buyer_checkout_message',
        'item_paid_time',
        'variation_attributes',
        'inventory_updated',
    ];

    protected $casts = [
        'variation_attributes' => 'array',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'actual_shipping_cost' => 'decimal:2',
        'actual_handling_cost' => 'decimal:2',
        'final_value_fee' => 'decimal:2',
        'item_paid_time' => 'datetime',
        'inventory_updated' => 'boolean',
    ];

    /**
     * Get the order this item belongs to
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product for this item
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Update inventory for this order item
     * Decrements the product stock quantity
     */
    public function updateInventory(): bool
    {
        if ($this->inventory_updated) {
            return true; // Already updated
        }

        if (!$this->product_id) {
            return false; // No linked product
        }

        $product = $this->product;
        if (!$product) {
            return false;
        }

        // Update product_stocks table
        $stock = ProductStock::where('product_id', $this->product_id)->first();
        if ($stock) {
            $newQuantity = max(0, $stock->quantity - $this->quantity);
            $stock->update(['quantity' => $newQuantity]);
        }

        // Mark as updated
        $this->update(['inventory_updated' => true]);

        return true;
    }

    /**
     * Restore inventory (for cancelled/refunded orders)
     */
    public function restoreInventory(): bool
    {
        if (!$this->inventory_updated) {
            return true; // Was never deducted
        }

        if (!$this->product_id) {
            return false;
        }

        $product = $this->product;
        if (!$product) {
            return false;
        }

        // Restore product_stocks
        $stock = ProductStock::where('product_id', $this->product_id)->first();
        if ($stock) {
            $stock->increment('quantity', $this->quantity);
        }

        // Mark as not updated
        $this->update(['inventory_updated' => false]);

        return true;
    }
}
