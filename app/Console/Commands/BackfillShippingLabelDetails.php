<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\ShippingService;
use Illuminate\Console\Command;

class BackfillShippingLabelDetails extends Command
{
    /**
     * Terminal-only. Not registered in routes/console.php scheduler — run manually only.
     *
     * @var string
     */
    protected $signature = 'orders:backfill-shipping-details
        {--order= : Only backfill a single order ID}
        {--limit=0 : Max number of orders to process (0 = no limit)}
        {--dry-run : Show what would be updated without saving}';

    protected $description = 'Backfill weight/dimensions for system-generated shipping labels by querying the carrier Track API (customer_reference/declared_value cannot be recovered — carriers do not echo them back)';

    public function handle(ShippingService $shippingService): int
    {
        $query = Order::whereNotNull('shipping_label_path')
            ->whereNotNull('shipping_id')
            ->whereNotNull('tracking_number');

        if ($orderId = $this->option('order')) {
            $query->where('id', $orderId);
        }

        if ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        $orders  = $query->with('shippingCarrier')->get();
        $dryRun  = (bool) $this->option('dry-run');

        $this->info("Found {$orders->count()} system-generated-label order(s) to check.");

        $stats = ['orders_updated' => 0, 'packages_updated' => 0, 'packages_skipped' => 0, 'errors' => 0];

        foreach ($orders as $order) {
            $carrier = $order->shippingCarrier;
            if (!$carrier) {
                $this->warn("Order #{$order->id}: carrier not found (shipping_id={$order->shipping_id}), skipping.");
                continue;
            }

            $service = $shippingService->resolveCarrierService($carrier);
            if (!$service) {
                $this->warn("Order #{$order->id}: carrier type '{$carrier->type}' not supported for tracking, skipping.");
                continue;
            }

            $packages   = $order->getAllTrackingNumbers();
            $orderDirty = false;

            foreach ($packages as $i => &$pkg) {
                if (empty($pkg['tracking_number'])) {
                    continue;
                }

                // Already have weight/dims saved (e.g. generated after the details-saving feature shipped) — skip
                if (($pkg['weight'] ?? null) !== null && ($pkg['length'] ?? null) !== null) {
                    $stats['packages_skipped']++;
                    continue;
                }

                try {
                    $result = $service->getTrackingStatus($pkg['tracking_number']);
                } catch (\Throwable $e) {
                    $this->error("Order #{$order->id} pkg {$pkg['tracking_number']}: {$e->getMessage()}");
                    $stats['errors']++;
                    continue;
                }

                $gotWeight = $result['weight'] !== null;
                $gotDims   = $result['length'] !== null;

                if (!$gotWeight && !$gotDims) {
                    $this->line("Order #{$order->id} pkg {$pkg['tracking_number']}: carrier returned no weight/dimension data.");
                    $stats['packages_skipped']++;
                    usleep(200000);
                    continue;
                }

                // Fill in identity fields too, in case this package predates the shipping_packages meta feature entirely
                $pkg['carrier']    = $pkg['carrier'] ?? $carrier->name;
                $pkg['label_path'] = $pkg['label_path'] ?? $order->shipping_label_path;

                if ($gotWeight) {
                    $pkg['weight']      = $result['weight'];
                    $pkg['weight_unit'] = $result['weight_unit'];
                }
                if ($gotDims) {
                    $pkg['length']         = $result['length'];
                    $pkg['width']          = $result['width'];
                    $pkg['height']         = $result['height'];
                    $pkg['dimension_unit'] = $result['dimension_unit'];
                }

                $orderDirty = true;
                $stats['packages_updated']++;

                $this->info("Order #{$order->id} pkg {$pkg['tracking_number']}: weight=" . ($result['weight'] ?? '-') . ' ' . ($result['weight_unit'] ?? '')
                    . ', dims=' . ($result['length'] ?? '-') . 'x' . ($result['width'] ?? '-') . 'x' . ($result['height'] ?? '-') . ' ' . ($result['dimension_unit'] ?? ''));

                // Avoid hammering the carrier API
                usleep(200000);
            }
            unset($pkg);

            if ($orderDirty) {
                $stats['orders_updated']++;
                if (!$dryRun) {
                    $order->setMeta('shipping_packages', $packages);
                }
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Orders updated: {$stats['orders_updated']}, packages updated: {$stats['packages_updated']}, packages skipped: {$stats['packages_skipped']}, errors: {$stats['errors']}");

        return self::SUCCESS;
    }
}
