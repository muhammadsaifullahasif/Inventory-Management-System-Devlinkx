<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when product stock is updated (order, adjustment, etc.).
 *
 * Listeners can use this to trigger inventory sync checks.
 */
class StockUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Product $product,
        public int $previousStock,
        public int $newStock,
        public string $source = 'unknown', // 'order', 'adjustment', 'import'
        public ?string $reference = null,  // Order ID, adjustment ID, etc.
    ) {}

    /**
     * Get the quantity change.
     */
    public function getQuantityChange(): int
    {
        return $this->newStock - $this->previousStock;
    }

    /**
     * Check if stock decreased.
     */
    public function stockDecreased(): bool
    {
        return $this->newStock < $this->previousStock;
    }

    /**
     * Check if stock increased.
     */
    public function stockIncreased(): bool
    {
        return $this->newStock > $this->previousStock;
    }
}
