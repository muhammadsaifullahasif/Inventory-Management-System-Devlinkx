<?php

namespace App\Jobs;

use Exception;
use App\Models\SalesChannel;
use App\Services\Ebay\EbayFinanceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncEbayFinancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;

    protected string $salesChannelId;
    protected int $daysBack;

    public function __construct(string $salesChannelId, int $daysBack = 7)
    {
        $this->salesChannelId = $salesChannelId;
        $this->daysBack = $daysBack;
    }

    public function handle(EbayFinanceSyncService $syncService): void
    {
        try {
            $salesChannel = SalesChannel::findOrFail($this->salesChannelId);

            $dateFrom = gmdate('Y-m-d\TH:i:s.000\Z', strtotime("-{$this->daysBack} days"));
            $dateTo = gmdate('Y-m-d\TH:i:s.000\Z');

            $result = $syncService->syncChannel($salesChannel, $dateFrom, $dateTo);

            Log::info('eBay finance sync job completed', [
                'sales_channel_id' => $this->salesChannelId,
                'result' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('eBay finance sync job failed', [
                'sales_channel_id' => $this->salesChannelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('eBay finance sync job failed completely', [
            'sales_channel_id' => $this->salesChannelId,
            'error' => $exception->getMessage(),
        ]);
    }
}
