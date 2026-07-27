<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\SalesChannelProduct;

/**
 * Calculates visible stock quantity for eBay listings.
 *
 * Core logic:
 * - Shows fixed visible quantity (e.g., 10) while central stock >= threshold
 * - When stock drops below threshold, shows actual available quantity
 * - ALL stores show the SAME quantity (no division between stores)
 *
 * Formula:
 *   if (centralStock >= threshold) → show threshold on all stores
 *   if (centralStock < threshold)  → show centralStock on all stores
 */
class VisibleStockCalculator
{
    /**
     * Calculate visible quantity for a single product-channel pair.
     *
     * @param Product $product The product to calculate for
     * @param SalesChannelProduct $listing The specific listing
     * @param int|null $activeStoreCount Number of active stores (auto-calculated if null)
     * @return VisibleStockResult
     */
    public function calculate(
        Product $product,
        SalesChannelProduct $listing,
        ?int $activeStoreCount = null
    ): VisibleStockResult {
        $centralStock = $product->available_stock;
        $visibleThreshold = $listing->visible_quantity ?? 10;

        // Get active store count if not provided
        if ($activeStoreCount === null) {
            $activeStoreCount = $this->getActiveStoreCount($product);
        }

        // Edge case: no active stores
        if ($activeStoreCount === 0) {
            return new VisibleStockResult(
                visibleQuantity: 0,
                centralStock: $centralStock,
                visibleThreshold: $visibleThreshold,
                activeStoreCount: 0,
                safeAllocation: 0,
                reason: 'no_active_stores'
            );
        }

        // Show same quantity on ALL stores (no division)
        // When stock >= threshold: show threshold (e.g., 10)
        // When stock < threshold: show actual stock
        if ($centralStock >= $visibleThreshold) {
            $visibleQuantity = $visibleThreshold;
            $reason = 'threshold_applied';
        } else {
            $visibleQuantity = $centralStock;
            $reason = 'below_threshold';
        }

        // Never show negative
        $visibleQuantity = max(0, $visibleQuantity);

        return new VisibleStockResult(
            visibleQuantity: $visibleQuantity,
            centralStock: $centralStock,
            visibleThreshold: $visibleThreshold,
            activeStoreCount: $activeStoreCount,
            safeAllocation: $visibleQuantity, // Same as visible for compatibility
            reason: $reason
        );
    }

    /**
     * Calculate visible quantities for all active listings of a product.
     *
     * @param Product $product
     * @return array<int, VisibleStockResult> Keyed by sales_channel_id
     */
    public function calculateForAllStores(Product $product): array
    {
        $activeListings = $this->getActiveListings($product);
        $activeStoreCount = $activeListings->count();

        $results = [];
        foreach ($activeListings as $listing) {
            $results[$listing->sales_channel_id] = $this->calculate(
                $product,
                $listing,
                $activeStoreCount
            );
        }

        return $results;
    }

    /**
     * Check if a sync is needed for a specific listing.
     *
     * Sync is needed when the visible quantity would change from
     * what was last synced.
     *
     * @param Product $product
     * @param SalesChannelProduct $listing
     * @return SyncDecision
     */
    public function shouldSync(Product $product, SalesChannelProduct $listing): SyncDecision
    {
        // Listing must be active and sync-enabled
        if ($listing->listing_status !== SalesChannelProduct::STATUS_ACTIVE) {
            return new SyncDecision(
                shouldSync: false,
                reason: 'listing_not_active',
                currentVisible: null,
                newVisible: null
            );
        }

        if (!$listing->sync_enabled) {
            return new SyncDecision(
                shouldSync: false,
                reason: 'sync_disabled',
                currentVisible: $listing->last_synced_quantity,
                newVisible: null
            );
        }

        $result = $this->calculate($product, $listing);
        $lastSynced = $listing->last_synced_quantity;

        // First sync ever
        if ($lastSynced === null) {
            return new SyncDecision(
                shouldSync: true,
                reason: 'never_synced',
                currentVisible: null,
                newVisible: $result->visibleQuantity
            );
        }

        // Check if quantity changed
        if ($result->visibleQuantity !== $lastSynced) {
            return new SyncDecision(
                shouldSync: true,
                reason: 'quantity_changed',
                currentVisible: $lastSynced,
                newVisible: $result->visibleQuantity
            );
        }

        return new SyncDecision(
            shouldSync: false,
            reason: 'no_change',
            currentVisible: $lastSynced,
            newVisible: $result->visibleQuantity
        );
    }

    /**
     * Get all listings that need syncing for a product.
     *
     * @param Product $product
     * @return array<array{listing: SalesChannelProduct, decision: SyncDecision, result: VisibleStockResult}>
     */
    public function getListingsNeedingSync(Product $product): array
    {
        $needsSync = [];
        $activeListings = $this->getActiveListings($product);
        $activeStoreCount = $activeListings->count();

        foreach ($activeListings as $listing) {
            $result = $this->calculate($product, $listing, $activeStoreCount);
            $decision = $this->shouldSync($product, $listing);

            if ($decision->shouldSync) {
                $needsSync[] = [
                    'listing' => $listing,
                    'decision' => $decision,
                    'result' => $result,
                ];
            }
        }

        return $needsSync;
    }

    /**
     * Get count of active stores for a product.
     */
    public function getActiveStoreCount(Product $product): int
    {
        return $product->activeSalesChannels()->count();
    }

    /**
     * Get active listings for a product.
     *
     * @return \Illuminate\Database\Eloquent\Collection<SalesChannelProduct>
     */
    protected function getActiveListings(Product $product): \Illuminate\Database\Eloquent\Collection
    {
        return SalesChannelProduct::where('product_id', $product->id)
            ->where('listing_status', SalesChannelProduct::STATUS_ACTIVE)
            ->where('sync_enabled', true)
            ->get();
    }
}
