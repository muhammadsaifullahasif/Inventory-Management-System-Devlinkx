<?php

namespace App\Jobs;

use Exception;
use App\Models\Rack;
use App\Models\Product;
use App\Models\Category;
use App\Models\Warehouse;
use App\Models\ProductStock;
use Illuminate\Support\Str;
use App\Models\SalesChannel;
use App\Models\EbayImportLog;
use App\Models\SalesChannelProduct;
use App\Services\Ebay\EbayApiClient;
use App\Services\Ebay\EbayService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportEbayListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;
    public $deleteWhenMissingModels = true;

    protected array $items;
    protected string $salesChannelId;
    protected int $batchNumber;
    protected int $totalBatches;
    protected ?int $importLogId;

    public function __construct(array $items, string $salesChannelId, int $batchNumber = 1, int $totalBatches = 1, ?int $importLogId = null)
    {
        $this->items = $items;
        $this->salesChannelId = $salesChannelId;
        $this->batchNumber = $batchNumber;
        $this->totalBatches = $totalBatches;
        $this->importLogId = $importLogId;
    }

    public function handle(EbayApiClient $client, EbayService $ebayService): void
    {
        Log::info('eBay Import Job Started', [
            'batch' => $this->batchNumber,
            'total_batches' => $this->totalBatches,
            'items_count' => count($this->items),
            'sales_channel_id' => $this->salesChannelId,
            'import_log_id' => $this->importLogId,
        ]);

        $insertedCount = 0;
        $updatedCount = 0;
        $errorCount = 0;
        $errors = [];

        // Get default warehouse and rack for new products
        $warehouse = Warehouse::where('is_default', true)->first();
        if (!$warehouse) {
            Log::error('eBay Import Job Error: No default warehouse found.');
            $this->updateImportLog(0, 0, count($this->items));
            throw new Exception('No default warehouse found.');
        }

        $rack = Rack::where('warehouse_id', $warehouse->id)->where('is_default', true)->first();
        if (!$rack) {
            Log::error('eBay Import Job Error: No default rack found', ['warehouse_id' => $warehouse->id]);
            $this->updateImportLog(0, 0, count($this->items));
            throw new Exception('No default rack found for the default warehouse.');
        }

        $salesChannel = SalesChannel::find($this->salesChannelId);
        if (!$salesChannel) {
            throw new Exception('Sales channel not found.');
        }

        // Ensure valid token for pushing updates to eBay
        $client->ensureValidToken($salesChannel);

        foreach ($this->items as $item) {
            try {
                $itemId = $item['item_id'] ?? '';
                // Use eBay SKU if exists, otherwise use ItemID
                $ebaySku = !empty($item['sku']) ? $item['sku'] : $itemId;

                // Find existing product by SKU OR ItemID
                $existingProduct = Product::where('sku', $ebaySku)
                    ->orWhere('sku', $itemId)
                    ->first();

                if ($existingProduct) {
                    // EXISTING PRODUCT: Push local stock/dimensions TO eBay
                    $this->syncExistingProductToEbay(
                        $existingProduct,
                        $item,
                        $salesChannel,
                        $ebayService
                    );
                    $updatedCount++;

                    Log::debug('eBay Product Synced (existing)', [
                        'batch' => $this->batchNumber,
                        'product_id' => $existingProduct->id,
                        'sku' => $existingProduct->sku,
                        'ebay_item_id' => $itemId,
                    ]);
                } else {
                    // NEW PRODUCT: Create locally from eBay data
                    $product = $this->createProductFromEbay(
                        $item,
                        $ebaySku,
                        $warehouse,
                        $rack
                    );

                    // Link to sales channel
                    $product->sales_channels()->syncWithoutDetaching([
                        $this->salesChannelId => [
                            'listing_url' => $item['listing_url'] ?? null,
                            'external_listing_id' => $itemId,
                            'listing_status' => SalesChannelProduct::STATUS_ACTIVE,
                            'last_synced_at' => now(),
                        ]
                    ]);

                    $insertedCount++;

                    Log::debug('eBay Product Created (new)', [
                        'batch' => $this->batchNumber,
                        'product_id' => $product->id,
                        'sku' => $ebaySku,
                        'ebay_item_id' => $itemId,
                    ]);
                }
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = [
                    'item_id' => $item['item_id'] ?? 'unknown',
                    'title' => $item['title'] ?? 'N/A',
                    'error' => $e->getMessage(),
                ];

                Log::error('eBay Import Job Item Error', [
                    'batch' => $this->batchNumber,
                    'item_id' => $item['item_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->updateImportLog($insertedCount, $updatedCount, $errorCount, $errors);

        Log::info('eBay Import Job Completed', [
            'batch' => $this->batchNumber,
            'total_batches' => $this->totalBatches,
            'inserted' => $insertedCount,
            'updated' => $updatedCount,
            'errors' => $errorCount,
        ]);
    }

    /**
     * Sync existing product TO eBay (push local stock/dimensions).
     */
    protected function syncExistingProductToEbay(
        Product $product,
        array $ebayItem,
        SalesChannel $salesChannel,
        EbayService $ebayService
    ): void {
        $itemId = $ebayItem['item_id'];

        // Calculate total stock from all warehouses/racks
        $totalQuantity = ProductStock::where('product_id', $product->id)
            ->where('active_status', '1')
            ->where('delete_status', '0')
            ->sum(DB::raw('CAST(quantity AS UNSIGNED)'));

        // Get dimensions from product meta
        $meta = $product->product_meta->pluck('meta_value', 'meta_key')->toArray();

        $fields = [
            'quantity' => (int) $totalQuantity,
        ];

        // Add dimensions if available
        if (!empty($meta['weight'])) {
            $fields['weight'] = (float) $meta['weight'];
            $fields['weight_unit'] = $meta['weight_unit'] ?? 'lbs';
        }
        if (!empty($meta['length'])) {
            $fields['length'] = (float) $meta['length'];
        }
        if (!empty($meta['width'])) {
            $fields['width'] = (float) $meta['width'];
        }
        if (!empty($meta['height'])) {
            $fields['height'] = (float) $meta['height'];
        }
        if (!empty($meta['dimension_unit'])) {
            $fields['dimension_unit'] = $meta['dimension_unit'];
        }

        // Push to eBay using ReviseItem (supports quantity + dimensions)
        $result = $ebayService->reviseItem($salesChannel, $itemId, $fields);

        // Update or create pivot record
        $pivotData = [
            'listing_url' => $ebayItem['listing_url'] ?? "https://www.ebay.com/itm/{$itemId}",
            'external_listing_id' => $itemId,
            'listing_status' => $result['success'] ? SalesChannelProduct::STATUS_ACTIVE : SalesChannelProduct::STATUS_ERROR,
            'listing_error' => $result['success'] ? null : ($result['errors'][0]['message'] ?? 'Sync failed'),
            'last_synced_at' => now(),
        ];

        if ($product->sales_channels()->where('sales_channel_id', $this->salesChannelId)->exists()) {
            $product->sales_channels()->updateExistingPivot($this->salesChannelId, $pivotData);
        } else {
            $product->sales_channels()->attach($this->salesChannelId, $pivotData);
        }
    }

    /**
     * Create new product from eBay listing data.
     */
    protected function createProductFromEbay(
        array $item,
        string $sku,
        Warehouse $warehouse,
        Rack $rack
    ): Product {
        // Get or create category - extract last segment from eBay category path
        // eBay returns: "Home & Garden:Household Supplies & Cleaning:Trash Cans & Wastebaskets"
        // We want: "Trash Cans & Wastebaskets"
        $fullCategoryPath = $item['category']['name'] ?? 'Uncategorized';
        $categoryParts = explode(':', $fullCategoryPath);
        $categoryName = trim(end($categoryParts)) ?: 'Uncategorized';

        $category = Category::whereLike('name', '%' . $categoryName . '%')->first();
        if (!$category) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
            ]);
        }
        if (!$category) {
            $category = Category::first();
        }

        if (!$category) {
            throw new Exception('No category found and unable to create a new one.');
        }

        // Download and save the first image
        $productImage = null;
        if (!empty($item['images'][0])) {
            $productImage = $this->downloadAndSaveImage($item['images'][0], $sku);
        }

        // Create product
        $product = Product::create([
            'sku' => $sku,
            'name' => $item['title'] ?? '',
            'barcode' => $sku,
            'category_id' => $category->id,
            'short_description' => '',
            'description' => $item['description'] ?? '',
            'price' => $item['price']['value'] ?? 0,
            'product_image' => $productImage,
        ]);

        // Create product meta
        $metaData = [
            ['product_id' => $product->id, 'meta_key' => 'regular_price', 'meta_value' => $item['regular_price']['value'] ?? $item['price']['value'] ?? ''],
            ['product_id' => $product->id, 'meta_key' => 'sale_price', 'meta_value' => $item['sale_price']['value'] ?? ''],
            ['product_id' => $product->id, 'meta_key' => 'weight', 'meta_value' => $item['dimensions']['weight'] ?? ''],
            ['product_id' => $product->id, 'meta_key' => 'weight_unit', 'meta_value' => $item['dimensions']['weight_unit'] ?? 'lbs'],
            ['product_id' => $product->id, 'meta_key' => 'length', 'meta_value' => $item['dimensions']['length'] ?? ''],
            ['product_id' => $product->id, 'meta_key' => 'width', 'meta_value' => $item['dimensions']['width'] ?? ''],
            ['product_id' => $product->id, 'meta_key' => 'height', 'meta_value' => $item['dimensions']['height'] ?? ''],
            ['product_id' => $product->id, 'meta_key' => 'dimension_unit', 'meta_value' => $item['dimensions']['dimension_unit'] ?? 'inches'],
            ['product_id' => $product->id, 'meta_key' => 'condition', 'meta_value' => $item['condition'] ?? ''],
            ['product_id' => $product->id, 'meta_key' => 'ebay_item_id', 'meta_value' => $item['item_id'] ?? ''],
        ];

        $product->product_meta()->upsert($metaData, ['product_id', 'meta_key'], ['meta_value']);

        // Store additional image URLs in meta
        if (!empty($item['images'])) {
            $imageUrls = array_slice($item['images'], 0, 5);
            foreach ($imageUrls as $index => $imageUrl) {
                $product->product_meta()->updateOrCreate(
                    ['product_id' => $product->id, 'meta_key' => 'image_url_' . ($index + 1)],
                    ['meta_value' => $imageUrl]
                );
            }
        }

        // Add stock to default warehouse/rack
        $quantity = max(0, (($item['quantity'] ?? 0) - ($item['quantity_sold'] ?? 0)));
        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rack_id' => $rack->id,
            'quantity' => $quantity,
            'active_status' => 1,
            'delete_status' => 0,
        ]);

        return $product;
    }

    /**
     * Download image from URL and save to storage.
     */
    protected function downloadAndSaveImage(string $url, string $sku): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                Log::warning('Failed to download eBay image', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            $imageContent = $response->body();
            $extension = $this->getImageExtension($response->header('Content-Type'));
            $filename = 'products/' . Str::slug($sku) . '-' . time() . '.' . $extension;

            Storage::disk('public')->put($filename, $imageContent);

            return $filename;
        } catch (Exception $e) {
            Log::warning('Error downloading eBay image', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get file extension from content type.
     */
    protected function getImageExtension(?string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    protected function updateImportLog(int $inserted, int $updated, int $failed, array $errors = []): void
    {
        if (!$this->importLogId) {
            return;
        }

        try {
            $importLog = EbayImportLog::find($this->importLogId);
            if ($importLog) {
                $importLog->addStatistics($inserted, $updated, $failed);
                $importLog->incrementCompletedBatches();

                if (!empty($errors)) {
                    $existingErrors = $importLog->error_details ?? [];
                    $importLog->update([
                        'error_details' => array_merge($existingErrors, [
                            'batch_' . $this->batchNumber => $errors
                        ])
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to update import log', [
                'import_log_id' => $this->importLogId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('eBay Import Job Failed', [
            'batch' => $this->batchNumber,
            'total_batches' => $this->totalBatches,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        if ($this->importLogId) {
            try {
                $importLog = EbayImportLog::find($this->importLogId);
                if ($importLog) {
                    $importLog->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                    ]);
                }
            } catch (Exception $e) {
                Log::error('Failed to update import log on job failure', [
                    'import_log_id' => $this->importLogId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
