<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\SalesChannelProduct;
use App\Models\InventorySyncLog;
use App\Services\Ebay\EbayApiClient;
use App\Services\Ebay\EbayService;
use App\Events\InventorySyncCompleted;
use App\Events\InventorySyncFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Orchestrates inventory synchronization to eBay stores.
 *
 * Responsibilities:
 * - Coordinates sync operations across multiple stores
 * - Handles order deductions and triggers sync checks
 * - Manages sync logging and error handling
 * - Implements batch operations for efficiency
 */
class InventorySyncService
{
    public function __construct(
        private VisibleStockCalculator $calculator,
        private EbayApiClient $ebayClient,
        private EbayService $ebayService,
    ) {}

    /**
     * Handle stock deduction from an order.
     *
     * Called after an order is processed. Checks if sync is needed
     * for any affected products and queues sync jobs.
     *
     * @param array<int, int> $productQuantities Product ID => quantity sold
     * @param string $orderReference Order ID for logging
     * @return array Summary of sync decisions
     */
    public function handleOrderDeduction(array $productQuantities, string $orderReference = ''): array
    {
        $summary = [
            'products_checked' => 0,
            'syncs_needed' => 0,
            'syncs_queued' => 0,
            'details' => [],
        ];

        foreach ($productQuantities as $productId => $quantitySold) {
            $product = Product::find($productId);
            if (!$product) {
                continue;
            }

            $summary['products_checked']++;

            $listingsNeedingSync = $this->calculator->getListingsNeedingSync($product);

            if (!empty($listingsNeedingSync)) {
                $summary['syncs_needed'] += count($listingsNeedingSync);

                foreach ($listingsNeedingSync as $syncData) {
                    // Queue async sync job
                    \App\Jobs\SyncInventoryToEbayJob::dispatch(
                        $product->id,
                        $syncData['listing']->sales_channel_id,
                        'order',
                        $orderReference
                    )->onQueue('inventory-sync');

                    $summary['syncs_queued']++;
                }

                $summary['details'][$productId] = [
                    'sku' => $product->sku,
                    'stock_after_order' => $product->available_stock,
                    'listings_queued' => count($listingsNeedingSync),
                ];
            }
        }

        return $summary;
    }

    /**
     * Sync a single product to a specific sales channel.
     *
     * @param Product $product
     * @param SalesChannel $channel
     * @param string $triggerSource 'order', 'manual', 'scheduled'
     * @param string|null $triggerReference
     * @return SyncResult
     */
    public function syncToStore(
        Product $product,
        SalesChannel $channel,
        string $triggerSource = 'manual',
        ?string $triggerReference = null
    ): SyncResult {
        $listing = SalesChannelProduct::where('product_id', $product->id)
            ->where('sales_channel_id', $channel->id)
            ->first();

        if (!$listing) {
            return new SyncResult(
                success: false,
                status: 'skipped',
                reason: 'listing_not_found',
                productId: $product->id,
                salesChannelId: $channel->id
            );
        }

        return $this->syncListing($product, $listing, $triggerSource, $triggerReference);
    }

    /**
     * Sync a specific listing to eBay.
     *
     * @param Product $product
     * @param SalesChannelProduct $listing
     * @param string $triggerSource
     * @param string|null $triggerReference
     * @return SyncResult
     */
    public function syncListing(
        Product $product,
        SalesChannelProduct $listing,
        string $triggerSource = 'manual',
        ?string $triggerReference = null
    ): SyncResult {
        $decision = $this->calculator->shouldSync($product, $listing);
        $previousQuantity = $listing->last_synced_quantity;

        // Create log entry
        $log = new InventorySyncLog([
            'product_id' => $product->id,
            'sales_channel_id' => $listing->sales_channel_id,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $decision->newVisible ?? 0,
            'central_stock' => $product->available_stock,
            'visible_threshold' => $listing->visible_quantity ?? 10,
            'status' => 'pending',
            'trigger_source' => $triggerSource,
            'trigger_reference' => $triggerReference,
            'ebay_item_id' => $listing->external_listing_id,
        ]);

        // Check if sync should proceed
        if (!$decision->shouldSync) {
            $log->status = 'skipped';
            $log->skip_reason = $decision->reason;
            $log->save();

            return new SyncResult(
                success: true,
                status: 'skipped',
                reason: $decision->reason,
                productId: $product->id,
                salesChannelId: $listing->sales_channel_id,
                logId: $log->id
            );
        }

        // Proceed with eBay API call
        try {
            $channel = SalesChannel::find($listing->sales_channel_id);
            if (!$channel) {
                throw new Exception('Sales channel not found');
            }

            // Ensure valid token
            $channel = $this->ebayClient->ensureValidToken($channel);

            // Call eBay API
            $result = $this->ebayService->reviseInventoryStatus(
                $channel,
                $listing->external_listing_id,
                $decision->newVisible
            );

            if (!$result['success']) {
                throw new Exception(
                    $result['errors'][0]['long_message']
                    ?? $result['errors'][0]['short_message']
                    ?? 'Unknown eBay error'
                );
            }

            // Update listing with sync info
            $listing->update([
                'last_synced_quantity' => $decision->newVisible,
                'last_synced_at' => now(),
                'last_sync_attempted_at' => now(),
                'last_sync_error' => null,
            ]);

            // Update log
            $log->status = 'success';
            $log->save();

            // Fire success event
            event(new InventorySyncCompleted($product, $listing, $decision->newVisible));

            Log::info('Inventory synced to eBay', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'sales_channel_id' => $listing->sales_channel_id,
                'ebay_item_id' => $listing->external_listing_id,
                'previous_qty' => $previousQuantity,
                'new_qty' => $decision->newVisible,
            ]);

            return new SyncResult(
                success: true,
                status: 'success',
                reason: 'synced',
                productId: $product->id,
                salesChannelId: $listing->sales_channel_id,
                previousQuantity: $previousQuantity,
                newQuantity: $decision->newVisible,
                logId: $log->id
            );

        } catch (Exception $e) {
            // Update listing with error
            $listing->update([
                'last_sync_attempted_at' => now(),
                'last_sync_error' => $e->getMessage(),
            ]);

            // Update log
            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->save();

            // Fire failure event
            event(new InventorySyncFailed($product, $listing, $e->getMessage()));

            Log::error('Inventory sync failed', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'sales_channel_id' => $listing->sales_channel_id,
                'error' => $e->getMessage(),
            ]);

            return new SyncResult(
                success: false,
                status: 'failed',
                reason: $e->getMessage(),
                productId: $product->id,
                salesChannelId: $listing->sales_channel_id,
                logId: $log->id
            );
        }
    }

    /**
     * Sync a product to ALL active stores.
     *
     * @param Product $product
     * @param string $triggerSource
     * @param string|null $triggerReference
     * @return array<SyncResult>
     */
    public function syncToAllStores(
        Product $product,
        string $triggerSource = 'manual',
        ?string $triggerReference = null
    ): array {
        $results = [];
        $activeListings = SalesChannelProduct::where('product_id', $product->id)
            ->where('listing_status', SalesChannelProduct::STATUS_ACTIVE)
            ->where('sync_enabled', true)
            ->get();

        foreach ($activeListings as $listing) {
            $results[] = $this->syncListing($product, $listing, $triggerSource, $triggerReference);
        }

        return $results;
    }

    /**
     * Sync only listings that need updating.
     *
     * More efficient than syncToAllStores as it skips unchanged listings.
     *
     * @param Product $product
     * @param string $triggerSource
     * @param string|null $triggerReference
     * @return array<SyncResult>
     */
    public function syncChangedListings(
        Product $product,
        string $triggerSource = 'manual',
        ?string $triggerReference = null
    ): array {
        $results = [];
        $listingsNeedingSync = $this->calculator->getListingsNeedingSync($product);

        foreach ($listingsNeedingSync as $syncData) {
            $results[] = $this->syncListing(
                $product,
                $syncData['listing'],
                $triggerSource,
                $triggerReference
            );
        }

        return $results;
    }

    /**
     * Queue async sync for a product.
     *
     * Non-blocking - dispatches job to queue.
     *
     * @param Product $product
     * @param string $triggerSource
     * @param string|null $triggerReference
     * @return int Number of jobs queued
     */
    public function queueSync(
        Product $product,
        string $triggerSource = 'manual',
        ?string $triggerReference = null
    ): int {
        $listingsNeedingSync = $this->calculator->getListingsNeedingSync($product);
        $queued = 0;

        foreach ($listingsNeedingSync as $syncData) {
            \App\Jobs\SyncInventoryToEbayJob::dispatch(
                $product->id,
                $syncData['listing']->sales_channel_id,
                $triggerSource,
                $triggerReference
            )->onQueue('inventory-sync');

            $queued++;
        }

        return $queued;
    }

    /**
     * Get sync status summary for a product.
     *
     * @param Product $product
     * @return array
     */
    public function getSyncStatus(Product $product): array
    {
        $centralStock = $product->available_stock;
        $calculations = $this->calculator->calculateForAllStores($product);
        $listings = SalesChannelProduct::where('product_id', $product->id)
            ->with('salesChannel:id,name')
            ->get();

        $status = [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'central_stock' => $centralStock,
            'active_stores' => count($calculations),
            'listings' => [],
        ];

        foreach ($listings as $listing) {
            $calc = $calculations[$listing->sales_channel_id] ?? null;
            $decision = $this->calculator->shouldSync($product, $listing);

            $status['listings'][] = [
                'sales_channel_id' => $listing->sales_channel_id,
                'sales_channel_name' => $listing->salesChannel->name ?? 'Unknown',
                'ebay_item_id' => $listing->external_listing_id,
                'listing_status' => $listing->listing_status,
                'sync_enabled' => $listing->sync_enabled,
                'visible_threshold' => $listing->visible_quantity,
                'last_synced_quantity' => $listing->last_synced_quantity,
                'last_synced_at' => $listing->last_synced_at?->toIso8601String(),
                'calculated_visible' => $calc?->visibleQuantity,
                'needs_sync' => $decision->shouldSync,
                'sync_reason' => $decision->reason,
                'last_error' => $listing->last_sync_error,
            ];
        }

        return $status;
    }

    /**
     * Get products that need syncing.
     *
     * Useful for scheduled sync jobs.
     *
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getProductsNeedingSync(int $limit = 100): \Illuminate\Support\Collection
    {
        // Get products with active listings that might need sync
        $productIds = SalesChannelProduct::where('listing_status', SalesChannelProduct::STATUS_ACTIVE)
            ->where('sync_enabled', true)
            ->distinct()
            ->pluck('product_id')
            ->take($limit);

        $products = Product::whereIn('id', $productIds)->get();
        $needsSync = collect();

        foreach ($products as $product) {
            $listings = $this->calculator->getListingsNeedingSync($product);
            if (!empty($listings)) {
                $needsSync->push([
                    'product' => $product,
                    'listings_count' => count($listings),
                ]);
            }
        }

        return $needsSync;
    }
}
