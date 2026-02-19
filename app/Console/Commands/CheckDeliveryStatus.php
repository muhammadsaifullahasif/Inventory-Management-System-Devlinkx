<?php

namespace App\Console\Commands;

use App\Services\ShippingService;
use Illuminate\Console\Command;

class CheckDeliveryStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:check-delivery-status
                            {--limit=100 : Maximum number of orders to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check delivery status for shipped orders and mark them as delivered when appropriate';

    /**
     * Execute the console command.
     */
    public function handle(ShippingService $shippingService): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Checking delivery status for up to {$limit} shipped orders...");

        $stats = $shippingService->checkAllPendingDeliveries($limit);

        $this->newLine();
        $this->info("Delivery Status Check Complete:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Orders Found', $stats['total']],
                ['Successfully Checked', $stats['checked']],
                ['Marked as Delivered', $stats['delivered']],
                ['Errors', $stats['errors']],
            ]
        );

        if ($stats['delivered'] > 0) {
            $this->info("{$stats['delivered']} order(s) marked as delivered.");
        }

        if ($stats['errors'] > 0) {
            $this->warn("{$stats['errors']} order(s) had tracking errors. Check logs for details.");
        }

        return Command::SUCCESS;
    }
}
