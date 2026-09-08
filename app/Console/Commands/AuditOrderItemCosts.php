<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderItem;
use App\Services\InventoryAccountingService;
use Illuminate\Support\Facades\DB;

class AuditOrderItemCosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:audit-costs
        {--product= : Only audit order items for this product ID}
        {--threshold=1 : Flag when cost_at_sale differs from reference cost by more than this percent}
        {--fix : Update flagged rows to the reference cost (default is a dry-run report)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find order items whose cost_at_sale disagrees with current purchase-cost data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fix = (bool) $this->option('fix');
        $threshold = (float) $this->option('threshold');
        $productId = $this->option('product');
        $inventoryService = new InventoryAccountingService();

        // Bundle component rows are excluded - their cost is rolled up into the
        // bundle summary row, so auditing them separately would be comparing
        // apples to oranges (same filter used to pick "main" line items when
        // rendering an order, see OrderController::show).
        $query = OrderItem::where(function ($q) {
                $q->whereNull('bundle_product_id')->orWhere('is_bundle_summary', true);
            })
            ->whereNotNull('product_id')
            ->whereNotNull('cost_at_sale')
            ->where('cost_at_sale', '>', 0)
            ->with(['order', 'product.bundleComponents']);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $items = $query->get();

        $this->info("Checking {$items->count()} order item(s) against current purchase-cost data...");

        $flagged = [];

        foreach ($items as $item) {
            $referenceCost = $this->referenceCostFor($item, $inventoryService);

            if ($referenceCost <= 0) {
                continue; // No purchase history to compare against - nothing to audit.
            }

            $storedCost = (float) $item->cost_at_sale;
            $diff = $referenceCost - $storedCost;
            $percentDiff = $storedCost > 0 ? abs($diff / $storedCost) * 100 : 100;

            if ($percentDiff > $threshold) {
                $flagged[] = [
                    'item' => $item,
                    'reference_cost' => $referenceCost,
                    'diff' => $diff,
                    'percent_diff' => $percentDiff,
                ];
            }
        }

        if (empty($flagged)) {
            $this->info('No discrepancies found.');
            return 0;
        }

        $this->table(
            ['Order #', 'SKU', 'cost_at_sale', 'Reference Cost', 'Diff', 'Diff %'],
            collect($flagged)->map(function ($row) {
                $item = $row['item'];
                return [
                    $item->order->order_number ?? $item->order_id,
                    $item->sku,
                    number_format($item->cost_at_sale, 4),
                    number_format($row['reference_cost'], 4),
                    number_format($row['diff'], 4),
                    number_format($row['percent_diff'], 2) . '%',
                ];
            })->all()
        );

        if ($fix) {
            DB::transaction(function () use ($flagged) {
                foreach ($flagged as $row) {
                    $row['item']->update(['cost_at_sale' => $row['reference_cost']]);
                }
            });
            $this->info(count($flagged) . ' order item(s) updated.');
        } else {
            $this->newLine();
            $this->info(count($flagged) . ' order item(s) flagged. This was a dry run - no changes made.');
            $this->comment('Re-run with --fix to apply the reference cost to these rows.');
        }

        return 0;
    }

    /**
     * The best available ground-truth cost for an order item, given current
     * purchase data. There is no historical cost ledger, so this is always
     * "what the last purchase price says today" rather than a reconstruction
     * of the true cost at the moment the item was sold.
     */
    protected function referenceCostFor(OrderItem $item, InventoryAccountingService $inventoryService): float
    {
        if ($item->is_bundle_summary) {
            return OrderItem::where('order_id', $item->order_id)
                ->where('bundle_product_id', $item->product_id)
                ->where('is_bundle_summary', false)
                ->get()
                ->sum(fn ($component) => $inventoryService->getLastPurchaseCost($component->product_id) * $component->quantity)
                / max(1, $item->quantity);
        }

        // A product can be flagged is_bundle after already being sold as a plain
        // line item, so its own SKU has no purchase history - fall back to
        // summing component costs, same as BackfillOrderItemCosts does.
        if ($item->product && $item->product->is_bundle) {
            return $item->product->bundleComponents->sum(
                fn ($component) => $inventoryService->getLastPurchaseCost($component->component_product_id) * $component->quantity_required
            );
        }

        return $inventoryService->getLastPurchaseCost($item->product_id);
    }
}
