<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\InventoryAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderItemProducts extends Command
{
    protected $signature = 'orders:backfill-products
                            {--dry-run : Show what would be done without making changes}
                            {--channel= : Only process orders from specific sales channel ID}
                            {--with-accounting : Also record sales revenue for items that get linked}';

    protected $description = 'Backfill product_id for order items by matching SKU, and optionally record sales revenue';

    protected int $itemsLinked = 0;
    protected int $itemsNotFound = 0;
    protected int $salesRevenueRecorded = 0;
    protected float $totalSalesValue = 0;

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $channelId = $this->option('channel');
        $withAccounting = $this->option('with-accounting');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║         BACKFILL ORDER ITEM PRODUCTS                         ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->info('');
        }

        // Get order items without product_id
        $query = OrderItem::whereNull('product_id')
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if ($channelId) {
            $query->whereHas('order', function ($q) use ($channelId) {
                $q->where('sales_channel_id', $channelId);
            });
            $this->info("Filtering by sales channel ID: {$channelId}");
        }

        $items = $query->with('order.salesChannel')->get();

        if ($items->isEmpty()) {
            $this->info('✓ All order items already have products linked.');
            return 0;
        }

        $this->info("Found {$items->count()} order item(s) without product_id");
        $this->info('');

        $progressBar = $this->output->createProgressBar($items->count());
        $progressBar->start();

        $accountingService = $withAccounting ? new InventoryAccountingService() : null;
        $notFoundSkus = [];

        foreach ($items as $item) {
            // Try to find product by SKU
            $product = Product::where('sku', $item->sku)->first();

            if ($product) {
                if (!$dryRun) {
                    DB::transaction(function () use ($item, $product, $withAccounting, $accountingService) {
                        $item->update(['product_id' => $product->id]);

                        // Record sales revenue if requested and order is paid/fulfilled
                        if ($withAccounting && $item->order) {
                            $order = $item->order;
                            if ($order->payment_status === 'paid' &&
                                in_array($order->fulfillment_status, ['fulfilled', 'shipped'])) {

                                // Check if sales revenue already recorded for this item
                                $existingEntry = \App\Models\JournalEntry::where('reference_type', 'order_sale')
                                    ->where('reference_id', $order->id)
                                    ->whereHas('lines', function ($q) use ($item) {
                                        $q->where('description', 'like', "%{$item->title}%");
                                    })
                                    ->exists();

                                if (!$existingEntry) {
                                    $entry = $accountingService->recordSalesRevenue($order, $item);
                                    if ($entry) {
                                        // Update entry date to match order date
                                        $entry->update(['entry_date' => $order->order_date ?? $order->created_at]);
                                        $this->salesRevenueRecorded++;
                                        $this->totalSalesValue += round($item->unit_price * $item->quantity, 2);
                                    }
                                }
                            }
                        }
                    });
                }
                $this->itemsLinked++;
            } else {
                $this->itemsNotFound++;
                if (!isset($notFoundSkus[$item->sku])) {
                    $notFoundSkus[$item->sku] = 0;
                }
                $notFoundSkus[$item->sku]++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info('');
        $this->info('');

        // Display summary
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                   BACKFILL SUMMARY                           ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        $rows = [
            ['Items Linked to Products', $this->itemsLinked],
            ['Items Not Found (SKU missing in products)', $this->itemsNotFound],
        ];

        if ($withAccounting) {
            $rows[] = ['Sales Revenue Entries Created', $this->salesRevenueRecorded];
            $rows[] = ['Total Sales Value', '$' . number_format($this->totalSalesValue, 2)];
        }

        $this->table(['Category', 'Count'], $rows);

        if (!empty($notFoundSkus) && count($notFoundSkus) <= 20) {
            $this->info('');
            $this->warn('SKUs not found in products table:');
            foreach ($notFoundSkus as $sku => $count) {
                $this->line("   - {$sku} ({$count} items)");
            }
        } elseif (!empty($notFoundSkus)) {
            $this->info('');
            $this->warn(count($notFoundSkus) . ' unique SKUs not found in products table');
        }

        $this->info('');

        if ($dryRun) {
            $this->warn('═══════════════════════════════════════════════════════════════');
            $this->warn('  This was a DRY RUN. No changes were made.');
            $this->warn('  Run without --dry-run to apply changes.');
            $this->warn('═══════════════════════════════════════════════════════════════');
        } else {
            $this->info('═══════════════════════════════════════════════════════════════');
            $this->info('  ✅ Order items backfill completed!');
            $this->info('═══════════════════════════════════════════════════════════════');
        }

        return 0;
    }
}
