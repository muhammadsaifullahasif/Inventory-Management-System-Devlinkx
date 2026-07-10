<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Services\Ebay\EbayFinanceSyncService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncEbayFinances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay:sync-finances
                            {--channel= : Specific sales channel ID to sync}
                            {--from= : Start date in Y-m-d format (defaults to 7 days ago)}
                            {--to= : End date in Y-m-d format (defaults to now)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync eBay Finances API transactions (fees, shipping labels, ad charges, payouts) for all active sales channels';

    /**
     * eBay's transactionDate filter is only reliable over shorter windows,
     * so wide backfill ranges are chunked into month-sized requests.
     */
    protected const CHUNK_DAYS = 30;

    public function handle(): int
    {
        $this->info('Starting eBay finance sync...');

        $query = SalesChannel::whereNotNull('refresh_token')
            ->whereNotNull('client_id')
            ->whereNotNull('client_secret');

        if ($channelId = $this->option('channel')) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('No active eBay sales channels found.');
            return 0;
        }

        $this->info("Found {$channels->count()} eBay sales channel(s) to sync.");

        $dateTo = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : now();
        $dateFrom = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : now()->subDays(7)->startOfDay();

        $this->info("Fetching transactions from {$dateFrom->toDateTimeString()} to {$dateTo->toDateTimeString()}");

        $syncService = app(EbayFinanceSyncService::class);

        $totalFetched = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalOrdersUpdated = 0;
        $channelsFailed = 0;

        foreach ($channels as $channel) {
            $this->newLine();
            $this->line("--------------------------------------------------");
            $this->info("Syncing channel: {$channel->name} (ID: {$channel->id})");
            $this->line("--------------------------------------------------");

            try {
                foreach ($this->chunkDateRange($dateFrom, $dateTo) as [$chunkFrom, $chunkTo]) {
                    $result = $syncService->syncChannel(
                        $channel,
                        $chunkFrom->format('Y-m-d\TH:i:s.000\Z'),
                        $chunkTo->format('Y-m-d\TH:i:s.000\Z')
                    );

                    $totalFetched += $result['fetched'];
                    $totalCreated += $result['created'];
                    $totalUpdated += $result['updated'];
                    $totalOrdersUpdated += $result['orders_updated'];

                    $this->line("  [OK] {$chunkFrom->toDateString()} to {$chunkTo->toDateString()}: fetched {$result['fetched']}, created {$result['created']}, updated {$result['updated']}, orders touched {$result['orders_updated']}");
                }
            } catch (Exception $e) {
                $channelsFailed++;
                $this->error("  [ERROR] Channel sync failed: {$e->getMessage()}");
                Log::channel('ebay')->error('eBay finance sync failed for channel', [
                    'sales_channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine(2);
        $this->info("===================================================");
        $this->info("           eBay Finance Sync Complete");
        $this->info("===================================================");
        $this->line("  Total Fetched:       {$totalFetched}");
        $this->line("  Total Created:       {$totalCreated}");
        $this->line("  Total Updated:       {$totalUpdated}");
        $this->line("  Orders Recalculated: {$totalOrdersUpdated}");

        if ($channelsFailed > 0) {
            $this->error("  Channels Failed: {$channelsFailed}");
        }

        if ($channelsFailed === $channels->count()) {
            $this->error("CRITICAL: All channels failed to sync!");
            return 1;
        }

        return 0;
    }

    /**
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    protected function chunkDateRange(Carbon $from, Carbon $to): array
    {
        $chunks = [];
        $cursor = $from->copy();

        while ($cursor->lt($to)) {
            $chunkEnd = $cursor->copy()->addDays(self::CHUNK_DAYS)->min($to);
            $chunks[] = [$cursor->copy(), $chunkEnd];
            $cursor = $chunkEnd;
        }

        return $chunks;
    }
}
