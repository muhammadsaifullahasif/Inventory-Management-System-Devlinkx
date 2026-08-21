<?php

namespace App\Console\Commands;

use App\Models\ProductStock;
use App\Notifications\LowStockNotification;
use App\Support\NotificationRecipients;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckLowStockLevels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:check-low-levels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check product stock levels against reorder thresholds and notify admins of low stock';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking stock levels against reorder thresholds...');

        $lowStocks = ProductStock::whereNotNull('reorder_threshold')
            ->whereColumn('quantity', '<=', 'reorder_threshold')
            ->where(function ($q) {
                $q->where('delete_status', 0)->orWhereNull('delete_status');
            })
            ->with(['product', 'warehouse'])
            ->get();

        if ($lowStocks->isEmpty()) {
            $this->info('No stock below reorder threshold.');
            return 0;
        }

        $admins = NotificationRecipients::admins();

        foreach ($lowStocks as $stock) {
            Notification::send($admins, new LowStockNotification($stock));
        }

        $this->info("{$lowStocks->count()} low-stock item(s) found. Notified {$admins->count()} admin(s).");

        return 0;
    }
}
