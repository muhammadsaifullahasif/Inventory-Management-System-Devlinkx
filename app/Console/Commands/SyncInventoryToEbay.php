<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SalesChannelProduct;
use App\Services\Inventory\InventorySyncService;
use App\Services\Inventory\VisibleStockCalculator;
use Illuminate\Console\Command;

/**
 * Artisan command for manual/scheduled inventory sync.
 *
 * Usage:
 *   php artisan inventory:sync                    # Sync all products needing update
 *   php artisan inventory:sync --product=123      # Sync specific product
 *   php artisan inventory:sync --dry-run          # Preview without syncing
 *   php artisan inventory:sync --force            # Force sync even if unchanged
 */
class SyncInventoryToEbay extends Command
{
    protected $signature = 'inventory:sync
                            {--product= : Sync specific product ID}
                            {--channel= : Sync specific sales channel ID}
                            {--dry-run : Preview without actually syncing}
                            {--force : Force sync even if quantity unchanged}
                            {--limit=100 : Maximum products to process}';

    protected $description = 'Sync inventory to eBay stores';

    public function __construct(
        private InventorySyncService $syncService,
        private VisibleStockCalculator $calculator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $limit = (int) $this->option('limit');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        // Single product sync
        if ($productId = $this->option('product')) {
            return $this->syncProduct((int) $productId, $dryRun, $force);
        }

        // Batch sync
        return $this->syncBatch($limit, $dryRun, $force);
    }

    /**
     * Sync a single product.
     */
    private function syncProduct(int $productId, bool $dryRun, bool $force): int
    {
        $product = Product::find($productId);
        if (!$product) {
            $this->error("Product #{$productId} not found");
            return 1;
        }

        $this->info("Processing: {$product->sku}");

        $channelId = $this->option('channel');
        if ($channelId) {
            return $this->syncProductToChannel($product, (int) $channelId, $dryRun, $force);
        }

        // Sync to all channels
        $calculations = $this->calculator->calculateForAllStores($product);

        if (empty($calculations)) {
            $this->warn('No active listings found');
            return 0;
        }

        $this->table(
            ['Channel', 'Visible Qty', 'Central Stock', 'Safe Alloc', 'Reason'],
            collect($calculations)->map(fn ($calc, $channelId) => [
                $channelId,
                $calc->visibleQuantity,
                $calc->centralStock,
                $calc->safeAllocation,
                $calc->reason,
            ])->toArray()
        );

        if ($dryRun) {
            $this->info('Dry run - no sync performed');
            return 0;
        }

        $results = $force
            ? $this->syncService->syncToAllStores($product, 'command')
            : $this->syncService->syncChangedListings($product, 'command');

        $this->displayResults($results);
        return 0;
    }

    /**
     * Sync product to specific channel.
     */
    private function syncProductToChannel(Product $product, int $channelId, bool $dryRun, bool $force): int
    {
        $listing = SalesChannelProduct::where('product_id', $product->id)
            ->where('sales_channel_id', $channelId)
            ->first();

        if (!$listing) {
            $this->error("No listing found for product #{$product->id} on channel #{$channelId}");
            return 1;
        }

        $calc = $this->calculator->calculate($product, $listing);
        $decision = $this->calculator->shouldSync($product, $listing);

        $this->table(['Metric', 'Value'], [
            ['Central Stock', $calc->centralStock],
            ['Visible Threshold', $calc->visibleThreshold],
            ['Active Stores', $calc->activeStoreCount],
            ['Safe Allocation', $calc->safeAllocation],
            ['Calculated Visible', $calc->visibleQuantity],
            ['Last Synced', $listing->last_synced_quantity ?? 'Never'],
            ['Needs Sync', $decision->shouldSync ? 'Yes' : 'No'],
            ['Reason', $decision->reason],
        ]);

        if ($dryRun) {
            $this->info('Dry run - no sync performed');
            return 0;
        }

        if (!$decision->shouldSync && !$force) {
            $this->info('No sync needed');
            return 0;
        }

        $channel = \App\Models\SalesChannel::find($channelId);
        $result = $this->syncService->syncToStore($product, $channel, 'command');

        $this->info("Result: {$result->status} - {$result->reason}");
        return $result->success ? 0 : 1;
    }

    /**
     * Sync batch of products.
     */
    private function syncBatch(int $limit, bool $dryRun, bool $force): int
    {
        $this->info("Scanning products for sync (limit: {$limit})...");

        $needsSync = $this->syncService->getProductsNeedingSync($limit);

        if ($needsSync->isEmpty()) {
            $this->info('No products need syncing');
            return 0;
        }

        $this->info("Found {$needsSync->count()} products needing sync");

        $this->table(
            ['Product ID', 'SKU', 'Listings'],
            $needsSync->map(fn ($item) => [
                $item['product']->id,
                $item['product']->sku,
                $item['listings_count'],
            ])->toArray()
        );

        if ($dryRun) {
            $this->info('Dry run - no sync performed');
            return 0;
        }

        if (!$this->confirm('Proceed with sync?')) {
            return 0;
        }

        $progressBar = $this->output->createProgressBar($needsSync->count());
        $progressBar->start();

        $totalSynced = 0;
        $totalFailed = 0;

        foreach ($needsSync as $item) {
            $results = $this->syncService->syncChangedListings($item['product'], 'command');

            foreach ($results as $result) {
                if ($result->wasSynced()) {
                    $totalSynced++;
                } elseif ($result->hasFailed()) {
                    $totalFailed++;
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Sync complete: {$totalSynced} synced, {$totalFailed} failed");
        return $totalFailed > 0 ? 1 : 0;
    }

    /**
     * Display sync results.
     */
    private function displayResults(array $results): void
    {
        $synced = collect($results)->filter(fn ($r) => $r->wasSynced())->count();
        $skipped = collect($results)->filter(fn ($r) => $r->wasSkipped())->count();
        $failed = collect($results)->filter(fn ($r) => $r->hasFailed())->count();

        $this->info("Results: {$synced} synced, {$skipped} skipped, {$failed} failed");

        foreach ($results as $result) {
            $status = match (true) {
                $result->wasSynced() => '<fg=green>SYNCED</>',
                $result->wasSkipped() => '<fg=yellow>SKIPPED</>',
                default => '<fg=red>FAILED</>',
            };
            $this->line("  Channel #{$result->salesChannelId}: {$status} - {$result->reason}");
        }
    }
}
