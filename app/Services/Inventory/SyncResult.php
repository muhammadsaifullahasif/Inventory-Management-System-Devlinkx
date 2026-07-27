<?php

namespace App\Services\Inventory;

/**
 * Result of a sync operation.
 *
 * Immutable value object containing sync operation outcome.
 */
readonly class SyncResult
{
    public function __construct(
        public bool $success,
        public string $status, // 'success', 'skipped', 'failed'
        public string $reason,
        public int $productId,
        public int $salesChannelId,
        public ?int $previousQuantity = null,
        public ?int $newQuantity = null,
        public ?int $logId = null,
    ) {}

    /**
     * Check if sync was actually performed (not skipped).
     */
    public function wasSynced(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if sync was skipped (no change needed).
     */
    public function wasSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    /**
     * Check if sync failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'reason' => $this->reason,
            'product_id' => $this->productId,
            'sales_channel_id' => $this->salesChannelId,
            'previous_quantity' => $this->previousQuantity,
            'new_quantity' => $this->newQuantity,
            'log_id' => $this->logId,
        ];
    }
}
