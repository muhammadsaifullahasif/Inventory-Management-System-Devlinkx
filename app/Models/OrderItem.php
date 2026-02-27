<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ProductController;
use App\Services\InventoryAccountingService;

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
        'cost_at_sale',
    ];

    protected $casts = [
        'variation_attributes' => 'array',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'actual_shipping_cost' => 'decimal:2',
        'actual_handling_cost' => 'decimal:2',
        'final_value_fee' => 'decimal:2',
        'cost_at_sale' => 'decimal:4',
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
     * Decrements the product stock quantity, records COGS, and syncs to all sales channels
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

        // Initialize inventory accounting service
        $inventoryAccountingService = new InventoryAccountingService();

        // Get current average cost BEFORE deducting stock (for COGS calculation)
        $avgCostAtSale = $inventoryAccountingService->getCurrentAverageCost($this->product_id);

        // Store the cost at sale for future reference
        $this->cost_at_sale = $avgCostAtSale;

        // Update product_stocks table - deduct from all stock locations for this product
        $stocks = ProductStock::where('product_id', $this->product_id)->get();
        $remainingQuantity = $this->quantity;

        foreach ($stocks as $stock) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $deductAmount = min($stock->quantity, $remainingQuantity);
            $newQuantity = max(0, $stock->quantity - $deductAmount);

            if ($newQuantity <= 0) {
                $stock->delete(); // Remove stock record if zero
            } else {
                $stock->update(['quantity' => $newQuantity]);
            }

            $remainingQuantity -= $deductAmount;
        }

        // Mark as updated
        $this->update([
            'inventory_updated' => true,
            'cost_at_sale' => $avgCostAtSale,
        ]);

        // Record COGS journal entry
        // DEBIT: Cost of Goods Sold (expense), CREDIT: Inventory Asset
        $order = $this->order;
        if ($order && $avgCostAtSale > 0) {
            $inventoryAccountingService->recordCOGS($order, $this, $avgCostAtSale);
        }

        // Sync inventory to all linked sales channels
        Log::info('Syncing inventory after order shipment', [
            'order_item_id' => $this->id,
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'quantity_deducted' => $this->quantity,
            'cost_at_sale' => $avgCostAtSale,
        ]);
        ProductController::syncProductInventoryToChannels($product);

        return true;
    }

    /**
     * Restore inventory (for cancelled/refunded orders)
     * Restores the product stock quantity, reverses COGS, and syncs to all sales channels
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

        // Initialize inventory accounting service
        $inventoryAccountingService = new InventoryAccountingService();

        // Use the cost at sale that was recorded when inventory was deducted
        $costAtSale = (float) ($this->cost_at_sale ?? 0);

        // Restore product_stocks - add to first available stock location or create new
        $stock = ProductStock::where('product_id', $this->product_id)->first();
        if ($stock) {
            $stock->increment('quantity', $this->quantity);
        } else {
            // Create a new stock entry at the default warehouse/rack if none exists
            $defaultWarehouse = \App\Models\Warehouse::where('is_default', '1')->first();
            $defaultRack = $defaultWarehouse ?
                \App\Models\Rack::where('warehouse_id', $defaultWarehouse->id)->where('is_default', '1')->first() : null;

            if ($defaultWarehouse && $defaultRack) {
                ProductStock::create([
                    'product_id' => $this->product_id,
                    'warehouse_id' => $defaultWarehouse->id,
                    'rack_id' => $defaultRack->id,
                    'quantity' => $this->quantity,
                    'avg_cost' => $costAtSale, // Restore with same cost
                ]);
            }
        }

        // Mark as not updated
        $this->update(['inventory_updated' => false]);

        // Reverse COGS journal entry
        // DEBIT: Inventory Asset, CREDIT: Cost of Goods Sold
        $order = $this->order;
        if ($order && $costAtSale > 0) {
            $inventoryAccountingService->reverseCOGS($order, $this, $costAtSale);
        }

        // Sync inventory to all linked sales channels
        Log::info('Syncing inventory after order cancellation/refund', [
            'order_item_id' => $this->id,
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'quantity_restored' => $this->quantity,
            'cost_reversed' => $costAtSale,
        ]);
        ProductController::syncProductInventoryToChannels($product);

        return true;
    }
}
