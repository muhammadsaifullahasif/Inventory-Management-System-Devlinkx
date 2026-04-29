<?php

namespace App\Observers;

use App\Models\ProductStock;
use App\Models\ProductBundleComponent;
use App\Models\Product;
use App\Events\StockUpdated;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Log;

class ProductStockObserver
{
    /**
     * Handle the ProductStock "created" event.
     */
    public function created(ProductStock $productStock): void
    {
        $this->syncBundlesContainingProduct($productStock->product_id, 'created');
        $this->dispatchStockUpdatedEvent($productStock, 0, (int) $productStock->quantity, 'stock_created');
    }

    /**
     * Handle the ProductStock "updated" event.
     */
    public function updated(ProductStock $productStock): void
    {
        // Only sync if quantity actually changed
        if ($productStock->isDirty('quantity')) {
            $this->syncBundlesContainingProduct($productStock->product_id, 'updated');

            // Dispatch StockUpdated event for inventory sync
            $previousQty = (int) $productStock->getOriginal('quantity');
            $newQty = (int) $productStock->quantity;
            $this->dispatchStockUpdatedEvent($productStock, $previousQty, $newQty, 'stock_adjustment');
        }
    }

    /**
     * Handle the ProductStock "deleted" event.
     */
    public function deleted(ProductStock $productStock): void
    {
        $this->syncBundlesContainingProduct($productStock->product_id, 'deleted');
    }

    /**
     * Handle the ProductStock "restored" event.
     */
    public function restored(ProductStock $productStock): void
    {
        $this->syncBundlesContainingProduct($productStock->product_id, 'restored');
    }

    /**
     * Sync all bundles that contain this product as a component.
     * When a component's stock changes, bundle stock needs to be recalculated and synced to sales channels.
     *
     * @param int $productId
     * @param string $action
     * @return void
     */
    protected function syncBundlesContainingProduct(int $productId, string $action): void
    {
        try {
            $bundleComponents = ProductBundleComponent::where('component_product_id', $productId)
                ->with('bundleProduct')
                ->get();

            if ($bundleComponents->isEmpty()) {
                return; // Product is not used in any bundles
            }

            foreach ($bundleComponents as $component) {
                $bundle = $component->bundleProduct;

                if (!$bundle || $bundle->delete_status == '1' || $bundle->active_status == '0') {
                    continue;
                }

                try {
                    ProductController::syncProductInventoryToChannels($bundle);
                } catch (\Exception $e) {
                    Log::error('ProductStockObserver: Failed to sync bundle inventory', [
                        'bundle_id' => $bundle->id,
                        'bundle_sku' => $bundle->sku,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('ProductStockObserver: Failed to sync bundles', [
                'product_id' => $productId,
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Dispatch StockUpdated event for inventory sync to eBay.
     *
     * Uses total product stock (across all warehouses) for the event.
     */
    protected function dispatchStockUpdatedEvent(
        ProductStock $productStock,
        int $previousQty,
        int $newQty,
        string $source
    ): void {
        try {
            $product = Product::find($productStock->product_id);
            if (!$product) {
                return;
            }

            // Check if product has active sales channels
            if (!$product->activeSalesChannels()->exists()) {
                return;
            }

            // Calculate total stock change
            // Note: For total stock, we need to consider this might be one of multiple warehouses
            $totalStock = $product->available_stock;
            $stockChange = $newQty - $previousQty;
            $previousTotalStock = $totalStock - $stockChange;

            event(new StockUpdated(
                product: $product,
                previousStock: max(0, $previousTotalStock),
                newStock: $totalStock,
                source: $source,
                reference: "product_stock:{$productStock->id}"
            ));

        } catch (\Exception $e) {
            Log::error('ProductStockObserver: Failed to dispatch StockUpdated event', [
                'product_id' => $productStock->product_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
