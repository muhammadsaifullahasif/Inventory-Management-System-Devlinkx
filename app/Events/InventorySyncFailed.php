<?php

namespace App\Events;

use App\Models\Product;
use App\Models\SalesChannelProduct;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when inventory sync to eBay fails.
 */
class InventorySyncFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Product $product,
        public SalesChannelProduct $listing,
        public string $error,
    ) {}
}
