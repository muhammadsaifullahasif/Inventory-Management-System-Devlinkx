<?php

namespace App\Listeners;

use App\Events\StockUpdated;
use App\Jobs\SyncInventoryToEbayJob;
use App\Services\Inventory\VisibleStockCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listens for stock updates and queues inventory sync jobs if needed.
 *
 * Implements ShouldQueue to run asynchronously and not block
 * the main request.
 */
class CheckInventorySyncOnStockUpdate implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue to use for this listener.
     */
    public string $queue = 'inventory-sync';

    public function __construct(
        private VisibleStockCalculator $calculator
    ) {}

    /**
     * Handle the event.
     */
    public function handle(StockUpdated $event): void
    {
        $product = $event->product;
        $listingsNeedingSync = $this->calculator->getListingsNeedingSync($product);

        if (empty($listingsNeedingSync)) {
            Log::debug('Stock updated but no sync needed', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'previous_stock' => $event->previousStock,
                'new_stock' => $event->newStock,
            ]);
            return;
        }

        Log::info('Stock update triggered inventory sync', [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'listings_count' => count($listingsNeedingSync),
            'source' => $event->source,
            'reference' => $event->reference,
        ]);

        // Queue sync jobs for each listing that needs it
        foreach ($listingsNeedingSync as $syncData) {
            SyncInventoryToEbayJob::dispatch(
                $product->id,
                $syncData['listing']->sales_channel_id,
                $event->source,
                $event->reference
            )->onQueue('inventory-sync');
        }
    }

    /**
     * Determine if the event should be queued.
     */
    public function shouldQueue(StockUpdated $event): bool
    {
        // Only queue if product has active listings
        return $event->product->activeSalesChannels()->exists();
    }
}
