<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RecalculateOrderTotals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:recalculate-totals
                            {--order= : Recalculate a single order ID}
                            {--channel= : Filter by sales_channel_id}
                            {--from= : Start date (Y-m-d) on order_date}
                            {--to= : End date (Y-m-d) on order_date}
                            {--ebay-only : Only orders with ebay_order_id}
                            {--bundles-only : Only orders that contain bundle items}
                            {--chunk=200 : Chunk size}
                            {--dry-run : Show changes without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate order subtotal/total from order items, excluding bundle component lines';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $query = Order::query()
                    ->select([
                        'id', 
                        'order_number', 
                        'sales_channel_id', 
                        'ebay_order_id', 
                        'order_date', 
                        'subtotal', 
                        'shipping_cost', 
                        'tax', 
                        'discount', 
                        'total', 
                    ])
                    ->with(['items:id,order_id,total_price,bundle_product_id,is_bundle_summary'])
                    ->orderBy('id');

        if ($orderId = $this->option('order')) {
            $query->whereKey((int) $orderId);
        }

        if ($channelId = $this->option('channel')) {
            $query->where('sales_channel_id', $channelId);
        }

        if ($from = $this->option('from')) {
            $query->whereDate('order_date', '>=', $from);
        }

        if ($to = $this->option('to')) {
            $query->whereDate('order_date', '<=', $to);
        }

        if ($this->option('ebay-only')) {
            $query->whereNotNull('ebay_order_id');
        }

        if ($this->option('bundles-only')) {
            $query->whereHas('items', function (Builder $q) {
                $q->whereNotNull('bundle_product_id')
                    ->orWhere('is_bundle_summary', true);
            });
        }

        $totalOrders = (clone $query)->count();

        if ($totalOrders === 0) {
            $this->info('No orders found for the selected filters');
            return self::SUCCESS;
        }

        $processed = 0;
        $changed = 0;
        $previewRows = [];

        $this->info('Processing {$totalOrders} order(s)...');
        $this->output->progressStart($totalOrders);

        $query->chunkById($chunk, function ($orders) use ($dryRun, &$processed, &$changed, &$previewRows) {
            foreach ($orders as $order) {
                // Count only main lines;
                // - regular items (bundle_product_id is null)
                // - bundle summary item (is_bundle_summary = true)
                // Exclude bundle component lines.
                $newSubtotal = round((float) $order->items
                    ->filter(function ($item) {
                        return is_null($item->bundle_product_id) || (bool) $item->is_bundle_summary;
                    })
                    ->sum(function ($item) {
                        return (float) $item->total_price;
                    }), 2);

                $shipping = round((float) ($order->shipping_cost ?? 0), 2);
                $tax = round((float) ($order->tax ?? 0), 2);
                $discount = round((float) ($order->discount ?? 0), 2);

                $newTotal = round($newSubtotal + $shipping + $tax - $discount, 2);

                $oldSubtotal = round((float) ($order->subtotal ?? 0), 2);
                $oldTotal = round((float) ($order->total ?? 0), 2);

                $needsUpdate = abs($oldSubtotal - $newSubtotal) > 0.009
                                || abs($oldTotal - $newTotal) > 0.009;
                
                if ($needsUpdate) {
                    $changed++;

                    if (count($previewRows) < 25) {
                        $previewRows[] = [
                            $order->id, 
                            $order->order_number, 
                            number_format($oldSubtotal, 2), 
                            number_format($newSubtotal, 2), 
                            number_format($oldTotal, 2), 
                            number_format($newTotal, 2), 
                        ];
                    }

                    if (!$dryRun) {
                        DB::transaction(function () use ($order, $newSubtotal, $newTotal) {
                            $order->update([
                                'subtotal' => $newSubtotal, 
                                'total' => $newTotal, 
                            ]);
                        });
                    }
                }

                $processed++;
                $this->output->progressAdvance();
            }
        });

        $this->output->progressFinish();
        $this->newLine(2);

        if (!empty($previewRows)) {
            $this->table(
                ['Order ID', 'Order #', 'Old Subtotal', 'New Subtotal', 'Old Total', 'New Total'], 
                $previewRows
            );
        }

        $this->info("Processed: {$processed}");
        $this->info("Changed: {$changed}");

        if ($dryRun) {
            $this->warn('Dry run only. No data was saved.');
        } else {
            $this->info('Recalculation completed and saved');
        }

        return self::SUCCESS;
    }
}
