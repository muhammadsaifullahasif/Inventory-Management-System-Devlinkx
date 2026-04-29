<?php

namespace App\Services\Inventory;

/**
 * Result of visible stock calculation.
 *
 * Immutable value object containing calculation details.
 */
readonly class VisibleStockResult
{
    public function __construct(
        public int $visibleQuantity,
        public int $centralStock,
        public int $visibleThreshold,
        public int $activeStoreCount,
        public int $safeAllocation,
        public string $reason,
    ) {}

    /**
     * Total exposure across all stores.
     */
    public function getTotalExposure(): int
    {
        return $this->visibleQuantity * $this->activeStoreCount;
    }

    /**
     * Whether safe allocation was applied (overselling protection triggered).
     */
    public function isSafeAllocationApplied(): bool
    {
        return $this->visibleQuantity < $this->visibleThreshold
            && $this->reason !== 'below_threshold';
    }

    /**
     * Whether stock is critically low (below threshold).
     */
    public function isLowStock(): bool
    {
        return $this->centralStock < $this->visibleThreshold;
    }

    /**
     * Whether this would result in out-of-stock listing.
     */
    public function isOutOfStock(): bool
    {
        return $this->visibleQuantity === 0;
    }

    public function toArray(): array
    {
        return [
            'visible_quantity' => $this->visibleQuantity,
            'central_stock' => $this->centralStock,
            'visible_threshold' => $this->visibleThreshold,
            'active_store_count' => $this->activeStoreCount,
            'safe_allocation' => $this->safeAllocation,
            'total_exposure' => $this->getTotalExposure(),
            'reason' => $this->reason,
            'is_low_stock' => $this->isLowStock(),
            'is_out_of_stock' => $this->isOutOfStock(),
            'safe_allocation_applied' => $this->isSafeAllocationApplied(),
        ];
    }
}
