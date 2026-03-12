<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductBundleComponent;
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
    }

    /**
     * Handle the ProductStock "updated" event.
     */
    public function updated(ProductStock $productStock): void
    {
        // Only sync if quantity actually changed
        if ($productStock->isDirty('quantity')) {
            $this->syncBundlesContainingProduct($productStock->product_id, 'updated');
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
            // Find all bundles that use this product as a component
            $bundleComponents = ProductBundleComponent::where('component_product_id', $productId)
                ->with('bundleProduct.sales_channels')
                ->get();

            if ($bundleComponents->isEmpty()) {
                return; // Product is not used in any bundles
            }

            Log::info('ProductStockObserver: Component stock changed, syncing bundles', [
                'product_id' => $productId,
                'action' => $action,
                'affected_bundles' => $bundleComponents->count(),
            ]);

            foreach ($bundleComponents as $component) {
                $bundle = $component->bundleProduct;

                if (!$bundle || $bundle->delete_status == '1' || $bundle->active_status == '0') {
                    continue;
                }

                // Recalculate bundle stock
                $newBundleStock = $bundle->available_stock;

                Log::info('ProductStockObserver: Bundle stock recalculated', [
                    'bundle_id' => $bundle->id,
                    'bundle_sku' => $bundle->sku,
                    'new_stock' => $newBundleStock,
                ]);

                // Sync to all connected sales channels
                foreach ($bundle->sales_channels as $channel) {
                    try {
                        ProductController::syncProductInventoryToChannel($bundle, $channel);

                        Log::info('ProductStockObserver: Bundle synced to sales channel', [
                            'bundle_id' => $bundle->id,
                            'bundle_sku' => $bundle->sku,
                            'channel_id' => $channel->id,
                            'channel_name' => $channel->name,
                            'stock' => $newBundleStock,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('ProductStockObserver: Failed to sync bundle to sales channel', [
                            'bundle_id' => $bundle->id,
                            'bundle_sku' => $bundle->sku,
                            'channel_id' => $channel->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
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
}
