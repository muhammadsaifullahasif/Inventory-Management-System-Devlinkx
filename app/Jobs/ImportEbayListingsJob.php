<?php

namespace App\Jobs;

use Exception;
use App\Models\Rack;
use App\Models\Product;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use App\Models\SalesChannel;
use Illuminate\Bus\Queueable;
use App\Models\EbayImportLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportEbayListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes per batch

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    protected array $items;
    protected string $salesChannelId;
    protected int $batchNumber;
    protected int $totalBatches;
    protected ?int $importLogId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $items, string $salesChannelId, int $batchNumber = 1, int $totalBatches = 1, ?int $importLogId = null)
    {
        $this->items = $items;
        $this->salesChannelId = $salesChannelId;
        $this->batchNumber = $batchNumber;
        $this->totalBatches = $totalBatches;
        $this->importLogId = $importLogId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
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

        // Get default warehouse and rack
        $warehouse = Warehouse::where('is_default', true)->first();
        if (!$warehouse) {
            Log::error('eBay Import Job Error: No default warehouse found.');
            $this->updateImportLog(0, 0, count($this->items));
            throw new Exception('No default warehouse found.');
        }

        $rack = Rack::where('warehouse_id', $warehouse->id)->where('is_default', true)->first();
        if (!$rack) {
            Log::error('eBay Import Job Error: No default found', ['warehouse_id' => $warehouse->id]);
            $this->updateImportLog(0, 0, count($this->items));
            throw new Exception('No default rack found for the default warehouse.');
        }

        foreach ($this->items as $item) {
            try {
                // Get or create category
                $category = Category::whereLike('name', '%' . $item['category']['name'] . '%')->first();
                if ($category == null) {
                    $category = Category::create([
                        'name' => $item['category']['name'],
                        'slug' => Str::slug($item['category']['name']),
                    ]);
                    if (!$category) {
                        $category = Category::first();
                    }
                }

                if (!$category) {
                    throw new Exception('No category found and unable to create a new one.');
                }

                $sku = $item['item_id'];

                // Check if product exists
                $existingProduct = Product::where('sku', $sku)->first();
                $productExists = $existingProduct !== null;

                // Create or update product
                $product = Product::updateOrCreate(
                    [
                        'sku' => $sku,
                    ],
                    [
                        'name' => $item['title'],
                        'barcode' => $sku,
                        'category_id' => $category->id,
                        'short_description' => '',
                        'description' => $item['description'] ?? '',
                        'price' => $item['price']['value'],
                    ]
                );

                if (!$product) {
                    throw new Exception('Failed to create/update product');
                }

                // Update product meta
                $metaData = [
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'item_id',
                        'meta_value' => $item['item_id'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'listing_url',
                        'meta_value' => $item['listing_url'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'listing_type',
                        'meta_value' => $item['listing_type'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'listing_status',
                        'meta_value' => $item['listing_status'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'weight',
                        'meta_value' => $item['dimensions']['weight'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'weight_unit',
                        'meta_value' => $item['dimensions']['weight_unit'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'length',
                        'meta_value' => $item['dimensions']['length'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'width',
                        'meta_value' => $item['dimensions']['width'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'height',
                        'meta_value' => $item['dimensions']['height'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'dimension_unit',
                        'meta_value' => $item['dimensions']['dimension_unit'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'condition',
                        'meta_value' => $item['condition'] ?? '',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'quantity_available',
                        'meta_value' => $item['quantity_available'] ?? '0',
                    ],
                    [
                        'product_id' => $product->id,
                        'meta_key' => 'sales_channel_id',
                        'meta_value' => $this->salesChannelId,
                    ],
                ];

                $product->product_meta()->upsert(
                    $metaData,
                    ['product_id', 'meta_key'],
                    ['meta_value']
                );

                // Handle product images
                if (!empty($item['images'])) {
                    $imageUrls = array_slice($item['images'], 0, 3);
                    foreach ($imageUrls as $imageIndex => $imageUrl) {
                        $product->product_meta()->updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'meta_key' => 'image_url_' . ($imageIndex + 1),
                            ],
                            [
                                'meta_value' => $imageUrl,
                            ]
                        );
                    }
                }

                // Update inventory
                $product->product_stocks()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse->id,
                        'rack_id' => $rack->id,
                    ],
                    [
                        'quantity' => $item['quantity_available'] ?? 0,
                    ]
                );

                if ($productExists) {
                    $updatedCount++;
                } else {
                    $insertedCount++;
                }

                Log::debug('eBay Product Processed', [
                    'batch' => $this->batchNumber,
                    'sku' => $sku,
                    'action' => $productExists ? 'updated' : 'inserted',
                ]);
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

        // Update import log with statistics
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
     * Update the import log with job statistics
     */
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

                // Add error details if any
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

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('eBay Import Job Failed', [
            'batch' => $this->batchNumber,
            'total_batches' => $this->totalBatches,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Update import log status to failed
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
