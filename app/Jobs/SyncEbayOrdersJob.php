<?php

namespace App\Jobs;

use Exception;
use App\Models\SalesChannel;
use App\Services\Ebay\EbayApiClient;
use App\Services\Ebay\EbayService;
use App\Services\Ebay\EbayOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncEbayOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;

    protected string $salesChannelId;
    protected int $daysBack;

    public function __construct(string $salesChannelId, int $daysBack = 30)
    {
        $this->salesChannelId = $salesChannelId;
        $this->daysBack = $daysBack;
    }

    public function handle(
        EbayApiClient $client,
        EbayService $ebayService,
        EbayOrderService $orderService,
    ): void {
        Log::info('Starting eBay order sync job', [
            'sales_channel_id' => $this->salesChannelId,
            'days_back' => $this->daysBack,
        ]);

        try {
            $salesChannel = SalesChannel::findOrFail($this->salesChannelId);
            $client->ensureValidToken($salesChannel);

            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime("-{$this->daysBack} days"));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');

            $result = $ebayService->getAllOrders($salesChannel, $createTimeFrom, $createTimeTo);
            $allOrders = $result['orders'];

            $syncedCount = 0;
            $updatedCount = 0;
            $errorCount = 0;

            foreach ($allOrders as $ebayOrder) {
                try {
                    $processResult = $orderService->processOrder($ebayOrder, $this->salesChannelId);
                    if ($processResult === 'created') {
                        $syncedCount++;
                    } elseif ($processResult === 'updated') {
                        $updatedCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error('Failed to process eBay order in job', [
                        'order_id' => $ebayOrder['order_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('eBay order sync job completed', [
                'total_orders' => count($allOrders),
                'synced' => $syncedCount,
                'updated' => $updatedCount,
                'errors' => $errorCount,
            ]);
        } catch (Exception $e) {
            Log::error('eBay order sync job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('eBay order sync job failed completely', [
            'sales_channel_id' => $this->salesChannelId,
            'error' => $exception->getMessage(),
        ]);
    }
}
