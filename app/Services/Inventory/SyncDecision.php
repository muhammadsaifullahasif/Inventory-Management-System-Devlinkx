<?php

namespace App\Services\Inventory;

/**
 * Decision result for whether a sync is needed.
 *
 * Immutable value object containing sync decision details.
 */
readonly class SyncDecision
{
    public function __construct(
        public bool $shouldSync,
        public string $reason,
        public ?int $currentVisible,
        public ?int $newVisible,
    ) {}

    /**
     * Get the quantity change (if any).
     */
    public function getQuantityChange(): ?int
    {
        if ($this->currentVisible === null || $this->newVisible === null) {
            return null;
        }
        return $this->newVisible - $this->currentVisible;
    }

    /**
     * Human-readable description of the decision.
     */
    public function getDescription(): string
    {
        return match ($this->reason) {
            'no_change' => 'No sync needed - quantity unchanged',
            'quantity_changed' => sprintf(
                'Sync needed - quantity changed from %d to %d',
                $this->currentVisible,
                $this->newVisible
            ),
            'never_synced' => sprintf(
                'Sync needed - first sync with quantity %d',
                $this->newVisible
            ),
            'listing_not_active' => 'No sync - listing not active',
            'sync_disabled' => 'No sync - sync disabled for this listing',
            default => "Decision: {$this->reason}",
        };
    }

    public function toArray(): array
    {
        return [
            'should_sync' => $this->shouldSync,
            'reason' => $this->reason,
            'current_visible' => $this->currentVisible,
            'new_visible' => $this->newVisible,
            'quantity_change' => $this->getQuantityChange(),
            'description' => $this->getDescription(),
        ];
    }
}
