<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderItem;
use App\Models\PurchaseItem;
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
            $referenceCost = $this->referenceCostFor($item);

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
     * The best available ground-truth cost for an order item: the lifetime
     * purchase-weighted-average across ALL received/partial purchase items
     * for the product, pooled across every warehouse/rack. product_stocks.avg_cost
     * is NOT used here - it's a moving average of what's currently on hand,
     * recalculated only on stock increases, so it drifts away from the true
     * lifetime average whenever sales happen between purchases. Last-purchase-price
     * is also not used - with more than one purchase it's just as wrong as
     * avg_cost, it only happens to be correct when there's a single purchase.
     */
    protected function referenceCostFor(OrderItem $item): float
    {
        if ($item->is_bundle_summary) {
            return OrderItem::where('order_id', $item->order_id)
                ->where('bundle_product_id', $item->product_id)
                ->where('is_bundle_summary', false)
                ->get()
                ->sum(fn ($component) => $this->lifetimeAverageCost($component->product_id) * $component->quantity)
                / max(1, $item->quantity);
        }

        // A product can be flagged is_bundle after already being sold as a plain
        // line item, so its own SKU has no purchase history - fall back to
        // summing component costs, same as BackfillOrderItemCosts does.
        if ($item->product && $item->product->is_bundle) {
            return $item->product->bundleComponents->sum(
                fn ($component) => $this->lifetimeAverageCost($component->component_product_id) * $component->quantity_required
            );
        }

        return $this->lifetimeAverageCost($item->product_id);
    }

    /**
     * sum(received_qty * price) / sum(received_qty) across every received/partial
     * purchase item for the product, regardless of warehouse or rack - the whole
     * system's quantity is pooled as one unit, matching how cost of goods should
     * be tracked (a unit is a unit no matter which rack it sits on).
     */
    protected function lifetimeAverageCost(int $productId): float
    {
        $purchaseItems = PurchaseItem::where('product_id', $productId)
            ->whereHas('purchase', function ($q) {
                $q->whereIn('purchase_status', ['received', 'partial']);
            })
            ->get();

        $totalQty = (float) $purchaseItems->sum('received_quantity');
        $totalValue = (float) $purchaseItems->sum(fn ($pi) => $pi->received_quantity * $pi->price);

        if ($totalQty <= 0) {
            return 0;
        }

        return round($totalValue / $totalQty, 4);
    }
}
