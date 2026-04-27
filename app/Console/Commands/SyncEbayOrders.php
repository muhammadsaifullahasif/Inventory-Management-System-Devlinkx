<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Services\Ebay\EbayApiClient;
use App\Services\Ebay\EbayService;
use App\Services\Ebay\EbayOrderService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEbayOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay:sync-orders
                            {--channel= : Specific sales channel ID to sync}
                            {--days= : Number of days back to fetch orders}
                            {--from= : Start date in Y-m-d format (overrides --days)}
                            {--to= : End date in Y-m-d format (defaults to now)}
                            {--today : Fetch only today\'s orders (default when no date options provided)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll and sync orders from eBay for all active sales channels';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting eBay order sync...');

        // Build query for eBay sales channels with valid credentials
        $query = SalesChannel::whereNotNull('refresh_token')
            ->whereNotNull('client_id')
            ->whereNotNull('client_secret');

        // Filter by specific channel if provided
        if ($channelId = $this->option('channel')) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('No active eBay sales channels found.');
            return 0;
        }

        $this->info("Found {$channels->count()} eBay sales channel(s) to sync.");

        // Calculate date range
        $dateTo = $this->option('to')
            ? now()->parse($this->option('to'))->endOfDay()
            : now();

        if ($this->option('from')) {
            $dateFrom = now()->parse($this->option('from'))->startOfDay();
        } elseif ($this->option('days')) {
            $days = (int) $this->option('days');
            $dateFrom = now()->subDays($days)->startOfDay();
        } else {
            // Default: today's orders only
            $dateFrom = now()->subDays(90)->startOfDay();
        }

        $this->info("Fetching orders from {$dateFrom->toDateTimeString()} to {$dateTo->toDateTimeString()}");

        $ebayClient = app(EbayApiClient::class);
        $ebayService = app(EbayService::class);
        $orderService = app(EbayOrderService::class);

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        foreach ($channels as $channel) {
            $this->newLine();
            $this->line("--------------------------------------------------");
            $this->info("Syncing channel: {$channel->name} (ID: {$channel->id})");
            $this->line("--------------------------------------------------");

            try {
                // Ensure we have a valid token
                $channel = $ebayClient->ensureValidToken($channel);
                $this->line("  [OK] Token validated");

                // Fetch all orders from eBay
                $this->line("  Fetching orders from eBay...");
                $result = $ebayService->getAllOrders(
                    $channel,
                    $dateFrom->toIso8601String(),
                    $dateTo->toIso8601String()
                );

                if (!$result['success']) {
                    $this->error("  [FAIL] Failed to fetch orders from eBay");
                    Log::channel('ebay')->error('Failed to fetch orders for sync', [
                        'sales_channel_id' => $channel->id,
                        'result' => $result,
                    ]);
                    $totalFailed++;
                    continue;
                }

                $orders = $result['orders'];
                $orderCount = count($orders);
                $this->info("  [OK] Found {$orderCount} order(s) on eBay");

                if ($orderCount === 0) {
                    continue;
                }

                $channelCreated = 0;
                $channelUpdated = 0;
                $channelSkipped = 0;

                foreach ($orders as $ebayOrder) {
                    try {
                        $action = $orderService->processOrder($ebayOrder, $channel->id);

                        if ($action === 'created') {
                            $channelCreated++;
                            $totalCreated++;
                        } elseif ($action === 'updated') {
                            $channelUpdated++;
                            $totalUpdated++;
                        } else {
                            $channelSkipped++;
                            $totalSkipped++;
                        }
                    } catch (Exception $e) {
                        Log::channel('ebay')->error('Failed to process order during sync', [
                            'sales_channel_id' => $channel->id,
                            'ebay_order_id' => $ebayOrder['order_id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $totalFailed++;
                    }
                }

                $this->line("  Results for {$channel->name}:");
                $this->line("    Created: {$channelCreated}");
                $this->line("    Updated: {$channelUpdated}");
                $this->line("    Skipped: {$channelSkipped}");

            } catch (Exception $e) {
                $this->error("  [ERROR] Error syncing channel: {$e->getMessage()}");
                Log::channel('ebay')->error('eBay order sync failed for channel', [
                    'sales_channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $totalFailed++;
            }
        }

        $this->newLine(2);
        $this->info("===================================================");
        $this->info("           eBay Order Sync Complete");
        $this->info("===================================================");
        $this->line("  Total Created: {$totalCreated}");
        $this->line("  Total Updated: {$totalUpdated}");
        $this->line("  Total Skipped: {$totalSkipped}");
        $this->line("  Total Failed:  {$totalFailed}");

        return $totalFailed > 0 ? 1 : 0;
    }
}
