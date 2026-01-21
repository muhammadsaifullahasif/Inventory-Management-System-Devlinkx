<?php

namespace App\Jobs;

use Exception;
use App\Models\Rack;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\SalesChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\EbayService;

class ImportEbayListings implements ShouldQueue, ShouldBeUnique
{
    use Queueable, Dispatchable, SerializesModels, InteractsWithQueue;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 3600; // 1 hour

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "import-ebay-listings-{$this->salesChannelId}";
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $salesChannelId,
        private string $jobType = 'active', // 'active', 'unsold', etc.
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EbayService $ebayService): void
    {
        try {
            Log::info('Starting eBay listings import job', [
                'sales_channel_id' => $this->salesChannelId,
                'job_type' => $this->jobType,
            ]);

            // Get sales channel
            $salesChannel = SalesChannel::findOrFail($this->salesChannelId);

            // Refresh token if needed
            if ($this->isTokenExpired($salesChannel)) {
                $this->refreshToken($salesChannel, $ebayService);
            }

            // Get default warehouse and rack
            $warehouse = Warehouse::where('is_default', true)->firstOrFail();
            $rack = Rack::where('warehouse_id', $warehouse->id)
                ->where('is_default', true)
                ->firstOrFail();

            // Fetch listings based on job type
            $result = match ($this->jobType) {
                'active' => $ebayService->getAllActiveListings($salesChannel),
                'unsold' => $ebayService->getAllUnsoldListings($salesChannel),
                default => throw new Exception("Unknown job type: {$this->jobType}"),
            };

            $allItems = $result['items'] ?? [];
            $totalListings = count($allItems);

            Log::info('eBay listings fetched', [
                'total_listings' => $totalListings,
                'sales_channel_id' => $this->salesChannelId,
            ]);

            // Process in chunks to manage memory
            $chunkSize = 100;
            $chunks = array_chunk($allItems, $chunkSize);

            $insertedCount = 0;
            $updatedCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($chunks as $chunk) {
                DB::beginTransaction();
                try {
                    foreach ($chunk as $item) {
                        try {
                            $result = $this->processItem($item, $warehouse, $rack, $salesChannel);
                            
                            if ($result['status'] === 'inserted') {
                                $insertedCount++;
                            } elseif ($result['status'] === 'updated') {
                                $updatedCount++;
                            }
                        } catch (Exception $e) {
                            $errorCount++;
                            $errors[] = [
                                'item_id' => $item['item_id'] ?? 'unknown',
                                'error' => $e->getMessage(),
                            ];
                            Log::warning('Error processing eBay item', [
                                'item_id' => $item['item_id'] ?? 'unknown',
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error('Error processing chunk', ['error' => $e->getMessage()]);
                    throw $e;
                }
            }

            // Log completion
            Log::info('eBay listings import completed', [
                'total_listings' => $totalListings,
                'inserted' => $insertedCount,
                'updated' => $updatedCount,
                'errors' => $errorCount,
                'sales_channel_id' => $this->salesChannelId,
            ]);

            // Dispatch event or send notification if needed
            // You can add event dispatching or notification sending here

        } catch (Exception $e) {
            Log::error('eBay listings import job failed', [
                'sales_channel_id' => $this->salesChannelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Fail the job
            $this->fail($e);
        }
    }

    /**
     * Process individual item
     */
    private function processItem(array $item, Warehouse $warehouse, Rack $rack, SalesChannel $salesChannel): array
    {
        $itemId = $item['item_id'];
        $sku = $item['sku'] ?? $itemId;

        // Check if product exists
        $product = Product::where('sku', $sku)->first();

        if ($product) {
            // Update existing product
            $product->update([
                'name' => $item['title'] ?? $product->name,
                'description' => $item['description'] ?? $product->description,
                'price' => $item['price'] ?? $product->price,
                'quantity' => $item['quantity_available'] ?? $product->quantity,
            ]);

            return ['status' => 'updated', 'product_id' => $product->id];
        } else {
            // Create new product
            $product = Product::create([
                'sku' => $sku,
                'name' => $item['title'] ?? 'Unknown Product',
                'description' => $item['description'] ?? '',
                'price' => $item['price'] ?? 0,
                'quantity' => $item['quantity_available'] ?? 0,
                'warehouse_id' => $warehouse->id,
                'rack_id' => $rack->id,
                'sales_channel_id' => $salesChannel->id,
                'external_id' => $itemId,
                'source' => 'ebay',
            ]);

            return ['status' => 'inserted', 'product_id' => $product->id];
        }
    }

    /**
     * Check if token is expired
     */
    private function isTokenExpired(SalesChannel $salesChannel): bool
    {
        if (!$salesChannel->token_expires_at) {
            return true;
        }

        return now()->isAfter($salesChannel->token_expires_at->subMinutes(5));
    }

    /**
     * Refresh the access token
     */
    private function refreshToken(SalesChannel $salesChannel, EbayService $ebayService): void
    {
        try {
            $response = $ebayService->refreshUserToken($salesChannel);

            $salesChannel->update([
                'access_token' => $response['access_token'],
                'token_expires_at' => now()->addSeconds($response['expires_in'] ?? 3600),
            ]);

            Log::info('eBay token refreshed successfully', [
                'sales_channel_id' => $salesChannel->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to refresh eBay token', [
                'sales_channel_id' => $salesChannel->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('ImportEbayListings job failed permanently', [
            'sales_channel_id' => $this->salesChannelId,
            'error' => $exception->getMessage(),
        ]);

        // You can send notifications here if needed
        // Notification::send($users, new JobFailedNotification($exception));
    }
}
