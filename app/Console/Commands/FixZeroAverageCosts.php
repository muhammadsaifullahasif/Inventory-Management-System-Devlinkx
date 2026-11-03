<?php

namespace App\Console\Commands;

use App\Models\OrderItem;
use App\Models\ProductStock;
use App\Models\PurchaseItem;
use App\Services\InventoryAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FixZeroAverageCosts extends Command
{
    protected $signature = 'inventory:fix-zero-costs
        {--product= : Only process this product ID}
        {--no-journal : Do not post the missing COGS journal entries}
        {--fix : Apply the changes (default is a dry-run report)}';

    protected $description = 'Repair product_stocks.avg_cost and order_items.cost_at_sale that were saved as 0 despite purchase history';

    public function handle(InventoryAccountingService $accounting): int
    {
        $fix = (bool) $this->option('fix');
        $productId = $this->option('product');

        // 1. Products with stock on hand at zero cost, or sale lines recorded at zero cost.
        $stockProducts = ProductStock::where('quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('avg_cost')->orWhere('avg_cost', '<=', 0))
            ->pluck('product_id');

        $saleProducts = $this->zeroCostItemsQuery()->pluck('order_items.product_id');

        $productIds = $stockProducts->merge($saleProducts)->unique()->filter()->values();
        if ($productId) {
            $productIds = $productIds->filter(fn ($id) => (int) $id === (int) $productId)->values();
        }

        if ($productIds->isEmpty()) {
            $this->info('Nothing to fix.');
            return self::SUCCESS;
        }

        // 2. Reference cost per product = lifetime purchase-weighted average
        //    (same definition as orders:audit-costs).
        $costs = [];
        $unrecoverable = [];
        foreach ($productIds as $id) {
            $cost = $this->lifetimeAverageCost((int) $id);
            if ($cost > 0) {
                $costs[(int) $id] = $cost;
            } else {
                $unrecoverable[] = (int) $id;
            }
        }

        $stockRows = ProductStock::whereIn('product_id', array_keys($costs))
            ->where('quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('avg_cost')->orWhere('avg_cost', '<=', 0))
            ->get();

        $items = $this->zeroCostItemsQuery()
            ->whereIn('order_items.product_id', array_keys($costs))
            ->select('order_items.*')
            ->with('order')
            ->get();

        // Bundle summaries are recomputed from their components, so they are
        // handled after components are fixed (not in $costs, which is per product).
        $bundleSummaries = $this->zeroCostItemsQuery(true)->select('order_items.*')->with('order')->get();
        if ($productId) {
            $bundleSummaries = $bundleSummaries->where('product_id', (int) $productId);
        }

        $this->info('Recoverable products: ' . count($costs));
        $this->table(
            ['Product ID', 'SKU', 'Reference cost', 'Stock rows to fix', 'Sale lines to fix'],
            collect($costs)->map(fn ($cost, $id) => [
                $id,
                optional(\App\Models\Product::find($id))->sku,
                number_format($cost, 4),
                $stockRows->where('product_id', $id)->count(),
                $items->where('product_id', $id)->count(),
            ])->values()->all()
        );

        if ($unrecoverable) {
            $this->warn('No purchase price on record - needs a manual cost: ');
            $this->table(['Product ID', 'SKU'], collect($unrecoverable)->map(fn ($id) => [
                $id, optional(\App\Models\Product::find($id))->sku,
            ])->all());
        }

        if (!$fix) {
            $this->newLine();
            $this->comment("Dry run - {$stockRows->count()} stock row(s), {$items->count()} sale line(s) would change. Re-run with --fix to apply.");
            return self::SUCCESS;
        }

        // 3. Back up affected rows before touching anything.
        $backup = storage_path('app/backups/fix-zero-costs-' . now()->format('Ymd-His') . '.json');
        File::ensureDirectoryExists(dirname($backup));
        File::put($backup, json_encode([
            'product_stocks' => $stockRows->map->only(['id', 'product_id', 'avg_cost'])->values(),
            'order_items' => $items->merge($bundleSummaries)->map->only(['id', 'order_id', 'product_id', 'cost_at_sale'])->values(),
        ], JSON_PRETTY_PRINT));
        $this->info("Backup written: {$backup}");

        $journalCount = 0;

        DB::transaction(function () use ($stockRows, $items, $bundleSummaries, $costs, $accounting, &$journalCount) {
            foreach ($stockRows as $row) {
                $row->update(['avg_cost' => $costs[$row->product_id]]);
            }

            foreach ($items as $item) {
                $cost = $costs[$item->product_id];
                $item->update(['cost_at_sale' => $cost]);

                // Component lines roll into their bundle summary's journal entry.
                if ($item->bundle_product_id === null && $item->order && !$this->option('no-journal')) {
                    if ($accounting->recordCOGS($item->order, $item, $cost)) {
                        $journalCount++;
                    }
                }
            }

            foreach ($bundleSummaries as $summary) {
                $componentCost = OrderItem::where('order_id', $summary->order_id)
                    ->where('bundle_product_id', $summary->product_id)
                    ->where('is_bundle_summary', false)
                    ->sum(DB::raw('COALESCE(cost_at_sale, 0) * quantity'));

                $perUnit = $componentCost / max(1, $summary->quantity);
                if ($perUnit > 0) {
                    $summary->update(['cost_at_sale' => $perUnit]);
                    if ($summary->order && !$this->option('no-journal') && $accounting->recordCOGS($summary->order, $summary, $perUnit)) {
                        $journalCount++;
                    }
                }
            }
        });

        $this->info("Updated {$stockRows->count()} stock row(s) and {$items->count()} sale line(s); posted {$journalCount} COGS journal entr" . ($journalCount === 1 ? 'y' : 'ies') . '.');

        return self::SUCCESS;
    }

    /**
     * Inventory-deducted, non-cancelled sale lines with a zero/null cost.
     * $bundleSummaries = true returns bundle summary lines; otherwise plain
     * lines and bundle components.
     */
    protected function zeroCostItemsQuery(bool $bundleSummaries = false)
    {
        return OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.product_id')
            ->where('order_items.inventory_updated', true)
            ->where('orders.order_status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNull('order_items.cost_at_sale')->orWhere('order_items.cost_at_sale', '<=', 0))
            ->where('order_items.is_bundle_summary', $bundleSummaries);
    }

    /** sum(received_qty * price) / sum(received_qty) across received/partial purchases. */
    protected function lifetimeAverageCost(int $productId): float
    {
        $purchaseItems = PurchaseItem::where('product_id', $productId)
            ->whereHas('purchase', fn ($q) => $q->whereIn('purchase_status', ['received', 'partial']))
            ->get();

        $totalQty = (float) $purchaseItems->sum('received_quantity');
        if ($totalQty <= 0) {
            return 0;
        }

        $totalValue = (float) $purchaseItems->sum(fn ($pi) => $pi->received_quantity * $pi->price);

        return round($totalValue / $totalQty, 4);
    }
}
