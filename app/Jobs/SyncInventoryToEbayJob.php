<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SalesChannel;
use App\Services\Inventory\InventorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for syncing inventory to eBay.
 *
 * Features:
 * - Prevents overlapping syncs for same product+channel
 * - Auto-retry with exponential backoff
 * - Detailed logging for debugging
 */
class SyncInventoryToEbayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of retry attempts.
     */
    public int $tries = 3;

    /**
     * Backoff intervals (seconds).
     */
    public array $backoff = [30, 60, 120];

    /**
     * Timeout for job execution (seconds).
     */
    public int $timeout = 120;

    public function __construct(
        public int $productId,
        public int $salesChannelId,
        public string $triggerSource = 'manual',
        public ?string $triggerReference = null
    ) {}

    /**
     * Prevent overlapping jobs for same product+channel.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("{$this->productId}-{$this->salesChannelId}"))
                ->releaseAfter(60)
                ->expireAfter(180),
        ];
    }

    /**
     * Unique job identifier for deduplication.
     */
    public function uniqueId(): string
    {
        return "{$this->productId}-{$this->salesChannelId}";
    }

    /**
     * Execute the job.
     */
    public function handle(InventorySyncService $syncService): void
    {
        $product = Product::find($this->productId);
        if (!$product) {
            Log::warning('SyncInventoryToEbayJob: Product not found', [
                'product_id' => $this->productId,
            ]);
            return;
        }

        $channel = SalesChannel::find($this->salesChannelId);
        if (!$channel) {
            Log::warning('SyncInventoryToEbayJob: Sales channel not found', [
                'sales_channel_id' => $this->salesChannelId,
            ]);
            return;
        }

        Log::info('SyncInventoryToEbayJob: Starting sync', [
            'product_id' => $this->productId,
            'sku' => $product->sku,
            'sales_channel_id' => $this->salesChannelId,
            'sales_channel' => $channel->name,
            'trigger' => $this->triggerSource,
            'attempt' => $this->attempts(),
        ]);

        $result = $syncService->syncToStore(
            $product,
            $channel,
            $this->triggerSource,
            $this->triggerReference
        );

        if ($result->hasFailed()) {
            Log::error('SyncInventoryToEbayJob: Sync failed', [
                'product_id' => $this->productId,
                'sales_channel_id' => $this->salesChannelId,
                'reason' => $result->reason,
            ]);

            // Will retry based on $tries and $backoff
            $this->fail(new \Exception("Inventory sync failed: {$result->reason}"));
            return;
        }

        Log::info('SyncInventoryToEbayJob: Sync completed', [
            'product_id' => $this->productId,
            'sales_channel_id' => $this->salesChannelId,
            'status' => $result->status,
            'new_quantity' => $result->newQuantity,
        ]);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncInventoryToEbayJob: Job failed permanently', [
            'product_id' => $this->productId,
            'sales_channel_id' => $this->salesChannelId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Job tags for Horizon dashboard.
     */
    public function tags(): array
    {
        return [
            'inventory-sync',
            "product:{$this->productId}",
            "channel:{$this->salesChannelId}",
        ];
    }
}
