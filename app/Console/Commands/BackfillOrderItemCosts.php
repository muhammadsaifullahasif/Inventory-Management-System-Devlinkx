<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderItem;
use App\Services\InventoryAccountingService;
use Illuminate\Support\Facades\DB;

class BackfillOrderItemCosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:backfill-costs {--dry-run : Preview changes without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing cost_at_sale values for order items using last purchase cost';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $inventoryService = new InventoryAccountingService();

        $this->info('Finding order items with zero or null cost_at_sale...');

        // Find items with 0 or null cost that have inventory_updated = true
        // Includes regular products, bundle components, and bundle summaries
        $items = OrderItem::whereRaw('(cost_at_sale = 0 OR cost_at_sale IS NULL)')
            ->where('inventory_updated', true)
            ->whereNotNull('product_id')
            ->with(['order', 'product', 'bundleProduct'])
            ->get();

        $this->info("Found {$items->count()} items with missing costs.");

        if ($items->isEmpty()) {
            $this->info('No items to backfill.');
            return 0;
        }

        // Separate bundle summaries from other items
        $regularItems = $items->where('is_bundle_summary', false);
        $bundleSummaries = $items->where('is_bundle_summary', true);

        $updated = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($items->count());

        // Process regular products and bundle components first
        foreach ($regularItems as $item) {
            $itemType = $item->bundle_product_id ? '[Bundle Component]' : '';
            $cost = $inventoryService->getLastPurchaseCost($item->product_id);

            if ($cost > 0) {
                if (!$dryRun) {
                    $item->update(['cost_at_sale' => $cost]);
                    // Refresh the model so bundle summaries get updated values
                    $item->refresh();
                }

                $this->newLine();
                $this->line("✓ Order {$item->order->order_number} - {$item->sku} {$itemType}: Updated to {$cost}");
                $updated++;
            } else {
                $this->newLine();
                $this->warn("✗ Order {$item->order->order_number} - {$item->sku} {$itemType}: No purchase history found");
                $skipped++;
            }

            $bar->advance();
        }

        // Now process bundle summaries (after components are updated)
        foreach ($bundleSummaries as $item) {
            $itemType = '[Bundle Summary]';
            $componentCost = OrderItem::where('order_id', $item->order_id)
                ->where('bundle_product_id', $item->product_id)
                ->where('is_bundle_summary', false)
                ->sum(DB::raw('COALESCE(cost_at_sale, 0) * quantity'));

            $cost = $componentCost / max(1, $item->quantity);

            if ($cost > 0) {
                if (!$dryRun) {
                    $item->update(['cost_at_sale' => $cost]);
                }

                $this->newLine();
                $this->line("✓ Order {$item->order->order_number} - {$item->sku} {$itemType}: Updated to {$cost}");
                $updated++;
            } else {
                $this->newLine();
                $this->warn("✗ Order {$item->order->order_number} - {$item->sku} {$itemType}: Components have no costs");
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("DRY RUN - No changes made");
        }

        $this->info("Summary:");
        $this->info("- Items updated: {$updated}");
        $this->info("- Items skipped: {$skipped}");

        if ($dryRun && $updated > 0) {
            $this->newLine();
            $this->info("Run without --dry-run to apply changes:");
            $this->comment("php artisan orders:backfill-costs");
        }

        return 0;
    }
}
