<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Rack;
use App\Models\Product;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use App\Models\ProductStock;
use App\Models\SalesChannel;
use Illuminate\Http\Request;
use App\Models\EbayImportLog;
use App\Services\Ebay\EbayApiClient;
use App\Services\Ebay\EbayService;
use App\Services\Ebay\EbayOrderService;
use App\Services\Ebay\EbayNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\ImportEbayListingsJob;
use App\Jobs\SyncEbayOrdersJob;

class EbayController extends Controller
{
    private const BATCH_SIZE = 50; // Process 50 items per job

    public function __construct(
        protected EbayApiClient $client,
        protected EbayService $ebayService,
        protected EbayOrderService $orderService,
        protected EbayNotificationService $notificationService,
    ) {}

    // =========================================
    // LISTING IMPORT & SYNC
    // =========================================

    /**
     * Get ALL active listings and dispatch import jobs
     */
    public function getAllActiveListings(string $id)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            Log::info('Starting eBay listings fetch', ['sales_channel_id' => $salesChannel->id]);

            $result = $this->ebayService->getAllActiveListings($salesChannel);
            $allItems = $result['items'];

            // Log all fetched listings to dedicated eBay log channel
            Log::channel('ebay')->info('eBay Import - All Fetched Listings', [
                'timestamp' => now()->toIso8601String(),
                'sales_channel_id' => $salesChannel->id,
                'total_listings' => count($allItems),
            ]);

            foreach ($allItems as $index => $item) {
                Log::channel('ebay')->debug('Listing #' . ($index + 1), [
                    'item_id' => $item['item_id'] ?? 'N/A',
                    'title' => $item['title'] ?? 'N/A',
                    'sku' => $item['sku'] ?? 'N/A',
                    'price' => $item['price'] ?? 'N/A',
                    'quantity' => $item['quantity'] ?? 0,
                    'quantity_available' => $item['quantity_available'] ?? 0,
                    'category' => $item['category'] ?? 'N/A',
                    'listing_status' => $item['listing_status'] ?? 'N/A',
                    'full_data' => $item,
                ]);
            }

            $totalListings = count($allItems);

            if ($totalListings === 0) {
                return redirect()->back()->with('info', 'No active listings found to import.');
            }

            // Create import log
            $importLog = EbayImportLog::create([
                'sales_channel_id' => $id,
                'total_listings' => $totalListings,
                'total_batcheds' => 0,
                'status' => 'pending',
                'started_at' => now(),
            ]);

            Log::info('eBay Listings Fetched - Dispatching to Queue', [
                'total_listings' => $totalListings,
                'sales_channel_id' => $id,
                'import_log_id' => $importLog->id,
            ]);

            // Split items into batches and dispatch jobs
            $batches = array_chunk($allItems, self::BATCH_SIZE);
            $totalBatches = count($batches);

            $importLog->update([
                'total_batches' => $totalBatches,
                'status' => 'processing',
            ]);

            foreach ($batches as $batchNumber => $batch) {
                ImportEbayListingsJob::dispatch(
                    $batch,
                    $id,
                    $batchNumber + 1,
                    $totalBatches,
                    $importLog->id
                )
                ->onQueue('ebay-imports')
                ->delay(now()->addSeconds($batchNumber * 2));
            }

            return redirect()->back()->with('success',
                "Successfully fetched {$totalListings} listings and dispatched {$totalBatches} import jobs to the queue. " .
                "Import ID: {$importLog->id}. The import will continue in the background. " .
                "You can check the status in the import logs."
            );

        } catch (\Exception $e) {
            Log::error('eBay Sync Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to fetch eBay listings: ' . $e->getMessage());
        }
    }

    /**
     * Sync listings - Import only NEW products from eBay that don't exist in system
     */
    /**
     * Sync listings from eBay.
     *
     * For each eBay listing:
     * - Match by SKU (if exists) OR ItemID
     * - If product EXISTS locally: Push local stock/dimensions TO eBay
     * - If product NOT found: Create new product from eBay data
     */
    public function syncListings(string $id)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            Log::info('Starting eBay sync listings', ['sales_channel_id' => $salesChannel->id]);

            $result = $this->ebayService->getAllActiveListings($salesChannel);
            $allItems = $result['items'];
            $totalFetched = count($allItems);

            if ($totalFetched === 0) {
                return redirect()->back()->with('info', 'No active listings found on eBay.');
            }

            // Collect all possible SKUs and ItemIDs for matching
            $ebaySkus = [];
            $ebayItemIds = [];
            foreach ($allItems as $item) {
                $ebayItemIds[] = $item['item_id'];
                if (!empty($item['sku'])) {
                    $ebaySkus[] = $item['sku'];
                }
            }

            // Find existing products by SKU OR ItemID
            $existingProducts = Product::where(function ($query) use ($ebaySkus, $ebayItemIds) {
                if (!empty($ebaySkus)) {
                    $query->whereIn('sku', $ebaySkus);
                }
                $query->orWhereIn('sku', $ebayItemIds);
            })->pluck('sku')->toArray();

            $existingCount = 0;
            $newCount = 0;
            foreach ($allItems as $item) {
                $ebaySku = !empty($item['sku']) ? $item['sku'] : $item['item_id'];
                if (in_array($ebaySku, $existingProducts) || in_array($item['item_id'], $existingProducts)) {
                    $existingCount++;
                } else {
                    $newCount++;
                }
            }

            Log::info('eBay Sync - Analysis', [
                'total_fetched' => $totalFetched,
                'existing_products' => $existingCount,
                'new_products' => $newCount,
            ]);

            // Create import log
            $importLog = EbayImportLog::create([
                'sales_channel_id' => $id,
                'total_listings' => $totalFetched,
                'total_batcheds' => 0,
                'status' => 'pending',
                'started_at' => now(),
            ]);

            Log::info('eBay Sync Listings - Dispatching to Queue', [
                'total_listings' => $totalFetched,
                'existing_to_sync' => $existingCount,
                'new_to_create' => $newCount,
                'sales_channel_id' => $id,
                'import_log_id' => $importLog->id,
            ]);

            // Split ALL items into batches (job handles existing vs new differently)
            $batches = array_chunk($allItems, self::BATCH_SIZE);
            $totalBatches = count($batches);

            $importLog->update([
                'total_batches' => $totalBatches,
                'status' => 'processing',
            ]);

            foreach ($batches as $batchNumber => $batch) {
                ImportEbayListingsJob::dispatch(
                    $batch,
                    $id,
                    $batchNumber + 1,
                    $totalBatches,
                    $importLog->id
                )
                ->onQueue('ebay-imports')
                ->delay(now()->addSeconds($batchNumber * 2));
            }

            return redirect()->back()->with('success',
                "Found {$totalFetched} eBay listings. " .
                "{$existingCount} existing products will be synced (local stock/dimensions → eBay). " .
                "{$newCount} new products will be created. " .
                "Processing in {$totalBatches} batch(es). Import ID: {$importLog->id}"
            );

        } catch (\Exception $e) {
            Log::error('eBay Sync Listings Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to sync eBay listings: ' . $e->getMessage());
        }
    }

    /**
     * Get ALL active listings synchronously (original method)
     * Kept for backward compatibility or manual sync if needed
     */
    public function getAllActiveListingsSync(string $id)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            $result = $this->ebayService->getAllActiveListings($salesChannel);
            $allItems = $result['items'];

            // Set PHP max execution time to 10 minutes
            set_time_limit(1200);

            // Tracking counters
            $totalListings = count($allItems);
            $insertedCount = 0;
            $updatedCount = 0;
            $errorCount = 0;
            $errors = [];

            // Get default warehouse and rack once
            $warehouse = Warehouse::where('is_default', true)->first();
            if (!$warehouse) {
                Log::error('eBay Sync Error: No default warehouse found');
                return redirect()->back()->with('error', 'No default warehouse found. Please set a default warehouse.');
            }

            $rack = Rack::where('warehouse_id', $warehouse->id)->where('is_default', true)->first();
            if (!$rack) {
                Log::error('eBay Sync Error: No default rack found for warehouse', ['warehouse_id' => $warehouse->id]);
                return redirect()->back()->with('error', 'No default rack found for the default warehouse.');
            }

            Log::info('eBay Sync Started (Synchronous)', [
                'total_listings' => $totalListings,
                'warehouse_id' => $warehouse->id,
                'rack_id' => $rack->id,
            ]);

            foreach ($allItems as $index => $item) {
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
                        throw new Exception('No category found or could be created');
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

                    if ($productExists) {
                        $updatedCount++;
                    } else {
                        $insertedCount++;
                    }

                } catch (Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'item_id' => $item['item_id'] ?? 'unknown',
                        'title' => $item['title'] ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];

                    Log::error('eBay Sync Item Error', [
                        'item_id' => $item['item_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $message = "Sync complete! Inserted: {$insertedCount}, Updated: {$updatedCount}";
            if ($errorCount > 0) {
                $message .= ", Errors: {$errorCount}";
            }

            Log::info('eBay Sync Complete (Synchronous)', [
                'total' => $totalListings,
                'inserted' => $insertedCount,
                'updated' => $updatedCount,
                'errors' => $errorCount,
            ]);

            return redirect()->back()->with('success', $message);

        } catch (Exception $e) {
            Log::error('eBay Sync Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    // =========================================
    // IMPORT LOG MANAGEMENT
    // =========================================

    /**
     * Get import log status
     */
    public function getImportStatus(string $importLogId)
    {
        try {
            $importLog = EbayImportLog::findOrFail($importLogId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $importLog->id,
                    'status' => $importLog->status,
                    'total_listings' => $importLog->total_listings,
                    'total_batches' => $importLog->total_batches,
                    'completed_batches' => $importLog->completed_batches,
                    'progress_percentage' => $importLog->getProgressPercentage(),
                    'items_inserted' => $importLog->items_inserted,
                    'items_updated' => $importLog->items_updated,
                    'items_failed' => $importLog->items_failed,
                    'started_at' => $importLog->started_at,
                    'completed_at' => $importLog->completed_at,
                    'is_complete' => $importLog->isComplete(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import log not found',
            ], 404);
        }
    }

    /**
     * Get the latest import log for a sales channel
     */
    public function getLatestImportLog(string $salesChannelId)
    {
        try {
            $importLog = EbayImportLog::where('sales_channel_id', $salesChannelId)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$importLog) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $importLog->id,
                    'status' => $importLog->status,
                    'total_listings' => $importLog->total_listings,
                    'total_batches' => $importLog->total_batches,
                    'completed_batches' => $importLog->completed_batches,
                    'progress_percentage' => $importLog->getProgressPercentage(),
                    'items_inserted' => $importLog->items_inserted,
                    'items_updated' => $importLog->items_updated,
                    'items_failed' => $importLog->items_failed,
                    'started_at' => $importLog->started_at?->format('Y-m-d H:i:s'),
                    'completed_at' => $importLog->completed_at?->format('Y-m-d H:i:s'),
                    'is_complete' => $importLog->isComplete(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch import log',
            ], 500);
        }
    }

    /**
     * List all import logs
     */
    public function listImportLogs(Request $request)
    {
        try {
            $query = EbayImportLog::query()
                ->with('salesChannel')
                ->orderBy('created_at', 'desc');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('sales_channel_id')) {
                $query->where('sales_channel_id', $request->sales_channel_id);
            }

            $importLogs = $query->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $importLogs,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch import logs',
            ], 500);
        }
    }

    // =========================================
    // ITEM DETAILS & UPDATE
    // =========================================

    /**
     * Get single item with full details
     */
    public function getItemDetails(string $id, string $itemId)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));
            $result = $this->ebayService->getItemDetails($salesChannel, $itemId);

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update an eBay listing using item_id (ReviseItem API)
     *
     * Supported fields: title, description, price, quantity, sku, condition_id
     */
    public function updateListing(array $data, string $id, string $itemId, bool $returnArray = false)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            // Build fields array for the service
            $fields = [];
            if (isset($data['title']) && !empty($data['title'])) {
                $fields['title'] = $data['title'];
            }
            if (isset($data['description'])) {
                $fields['description'] = $data['description'];
            }
            if (isset($data['price'])) {
                $fields['price'] = $data['price'];
                $fields['currency'] = $data['currency'] ?? 'USD';
            }
            if (isset($data['quantity'])) {
                $fields['quantity'] = (int) $data['quantity'];
            }
            if (isset($data['sku'])) {
                $fields['sku'] = $data['sku'];
            }
            if (isset($data['condition_id'])) {
                $fields['condition_id'] = (int) $data['condition_id'];
            }

            if (empty($fields)) {
                $error = ['success' => false, 'message' => 'No fields provided to update'];
                return $returnArray ? $error : $this->errorResponse('No fields provided to update', 400);
            }

            Log::info('eBay ReviseItem Request', [
                'item_id' => $itemId,
                'data' => $data,
            ]);

            $result = $this->ebayService->reviseItem($salesChannel, $itemId, $fields);

            Log::info('eBay ReviseItem Response', [
                'item_id' => $itemId,
                'result' => $result,
            ]);

            return $returnArray ? $result : response()->json($result);
        } catch (Exception $e) {
            Log::error('eBay ReviseItem Error', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            $error = ['success' => false, 'message' => $e->getMessage()];
            return $returnArray ? $error : $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update listing quantity only (quick method)
     */
    public function updateListingQuantity(Request $request, string $id, string $itemId)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            $quantity = (int) $request->input('quantity', 0);
            $result = $this->ebayService->reviseItem($salesChannel, $itemId, ['quantity' => $quantity]);

            return response()->json($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update listing price only (quick method)
     */
    public function updateListingPrice(Request $request, string $id, string $itemId)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            $price = $request->input('price');
            $currency = $request->input('currency', 'USD');

            if (!$price) {
                return $this->errorResponse('Price is required', 400);
            }

            $result = $this->ebayService->reviseItem($salesChannel, $itemId, [
                'price' => $price,
                'currency' => $currency,
            ]);

            return response()->json($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================
    // ORDER SYNC
    // =========================================

    /**
     * Sync orders from eBay
     */
    public function syncOrders(string $id)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime('-90 days'));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');

            Log::info('Starting eBay order sync', [
                'sales_channel_id' => $id,
                'from' => $createTimeFrom,
                'to' => $createTimeTo,
            ]);

            $result = $this->ebayService->getAllOrders($salesChannel, $createTimeFrom, $createTimeTo);
            $allOrders = $result['orders'];
            $totalOrders = count($allOrders);

            if ($totalOrders === 0) {
                return redirect()->back()->with('info', 'No orders found to sync.');
            }

            // Process orders
            $syncedCount = 0;
            $updatedCount = 0;
            $errorCount = 0;

            foreach ($allOrders as $ebayOrder) {
                try {
                    $processResult = $this->orderService->processOrder($ebayOrder, $id);
                    if ($processResult === 'created') {
                        $syncedCount++;
                    } elseif ($processResult === 'updated') {
                        $updatedCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error('Failed to process eBay order', [
                        'order_id' => $ebayOrder['order_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $message = "Order sync complete! New: {$syncedCount}, Updated: {$updatedCount}";
            if ($errorCount > 0) {
                $message .= ", Errors: {$errorCount}";
            }

            Log::info('eBay order sync completed', [
                'total' => $totalOrders,
                'synced' => $syncedCount,
                'updated' => $updatedCount,
                'errors' => $errorCount,
            ]);

            return redirect()->back()->with('success', $message);

        } catch (Exception $e) {
            Log::error('eBay Order Sync Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to sync orders: ' . $e->getMessage());
        }
    }

    /**
     * Sync orders via queue (background processing)
     */
    public function syncOrdersQueue(string $id)
    {
        try {
            $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            SyncEbayOrdersJob::dispatch($id)
                ->onQueue('ebay-imports');

            return redirect()->back()->with('success', 'Order sync job dispatched. Orders will be synced in the background.');

        } catch (Exception $e) {
            Log::error('eBay Order Sync Queue Error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to dispatch order sync: ' . $e->getMessage());
        }
    }

    /**
     * Get orders list (API endpoint)
     */
    public function getOrders(Request $request, string $id)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime("-90 days"));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 500);

            $result = $this->ebayService->getOrders($salesChannel, $createTimeFrom, $createTimeTo, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get eBay orders with date range and pagination
     */
    public function getEbayOrders(Request $request, string $id)
    {
        try {
            $salesChannel = $this->client->ensureValidToken(SalesChannel::findOrFail($id));

            $daysBack = $request->input('days', 30);
            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime("-{$daysBack} days"));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 100);

            $result = $this->ebayService->getOrders($salesChannel, $createTimeFrom, $createTimeTo, $page, $perPage);

            return response()->json([
                'success' => true,
                'sales_channel_id' => $salesChannel->id,
                'sales_channel_name' => $salesChannel->name,
                'fetched_at' => now()->toIso8601String(),
                'date_range' => [
                    'from' => $createTimeFrom,
                    'to' => $createTimeTo,
                    'days_back' => $daysBack,
                ],
                'data' => $result,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================
    // WEBHOOK HANDLING
    // =========================================

    /**
     * Handle eBay webhook notifications
     * Supports both Platform Notifications (XML) and Commerce Notification API (JSON)
     */
    public function handleEbayOrderWebhook(Request $request, string $id)
    {
        Log::channel('ebay')->info('>>>>>> WEBHOOK REQUEST RECEIVED <<<<<<');
        Log::channel('ebay')->info(json_encode([
            'timestamp' => now()->toIso8601String(),
            'sales_channel_id' => $id,
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'ip' => $request->ip(),
        ], JSON_PRETTY_PRINT));

        $salesChannel = SalesChannel::find($id);

        if (!$salesChannel) {
            Log::channel('ebay')->error('eBay Webhook: Sales channel not found', ['id' => $id]);
            return response()->json(['error' => 'Sales channel not found'], 404);
        }

        // Check if this is a challenge request (Commerce Notification API)
        if ($request->has('challenge_code')) {
            return $this->handleChallengeRequest($request, $salesChannel);
        }

        // Determine notification type based on content type
        $contentType = $request->header('Content-Type', '');

        if (str_contains($contentType, 'application/json')) {
            return $this->handleCommerceApiNotification($request, $salesChannel);
        } else {
            return $this->handlePlatformNotification($request, $salesChannel);
        }
    }

    /**
     * Handle challenge request from Commerce Notification API
     */
    protected function handleChallengeRequest(Request $request, SalesChannel $salesChannel)
    {
        $challengeCode = $request->input('challenge_code');
        $verificationToken = $salesChannel->notification_verification_token;
        $endpoint = $salesChannel->webhook_url ?? route('ebay.orders.webhook', $salesChannel->id);

        $hashInput = $challengeCode . $verificationToken . $endpoint;
        $challengeResponse = hash('sha256', $hashInput);

        Log::info('eBay Challenge Request handled', [
            'sales_channel_id' => $salesChannel->id,
        ]);

        return response()->json(['challengeResponse' => $challengeResponse]);
    }

    /**
     * Handle Commerce Notification API notifications (JSON)
     */
    protected function handleCommerceApiNotification(Request $request, SalesChannel $salesChannel)
    {
        $payload = $request->all();
        $timestamp = now();
        $topic = $payload['metadata']['topic'] ?? 'unknown';

        $notificationData = [
            'timestamp' => $timestamp->toIso8601String(),
            'notification_type' => $topic,
            'sales_channel_id' => $salesChannel->id,
            'sales_channel_name' => $salesChannel->name,
            'data' => $payload,
        ];

        $this->saveNotificationToFile($topic, $notificationData, $timestamp);

        Log::channel('ebay')->info("Commerce API Notification received: {$topic}", [
            'timestamp' => $timestamp->toIso8601String(),
            'sales_channel_id' => $salesChannel->id,
            'file' => $this->getNotificationFileName($topic, $timestamp),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Handle Platform Notifications (XML from Trading API)
     */
    protected function handlePlatformNotification(Request $request, SalesChannel $salesChannel)
    {
        $rawContent = $request->getContent();
        $timestamp = now();

        try {
            // Clean SOAP namespaces and parse
            $cleanedXml = $this->client->cleanSoapXml($rawContent);

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($cleanedXml);
            $xmlErrors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors(false);

            if ($xml === false || !empty($xmlErrors)) {
                Log::channel('ebay')->warning('eBay Platform Notification: XML parsing issues, saving raw content', [
                    'timestamp' => $timestamp->toIso8601String(),
                    'sales_channel_id' => $salesChannel->id,
                    'errors' => array_map(fn($e) => $e->message, $xmlErrors),
                ]);

                $notificationData = [
                    'timestamp' => $timestamp->toIso8601String(),
                    'notification_type' => 'RAW_XML_PARSE_ERROR',
                    'sales_channel_id' => $salesChannel->id,
                    'sales_channel_name' => $salesChannel->name,
                    'parse_errors' => array_map(fn($e) => trim($e->message), $xmlErrors),
                    'raw_content' => $rawContent,
                    'cleaned_content' => $cleanedXml,
                ];

                $this->saveNotificationToFile('RAW_NOTIFICATION', $notificationData, $timestamp);

                return response('OK', 200);
            }

            // Get the notification type from the root element
            $notificationType = $xml->getName();
            $notificationXml = $xml;

            // For SOAP notifications, extract the actual notification from Body
            if ($notificationType === 'Envelope') {
                $body = $xml->Body ?? null;
                if ($body && $body->children()->count() > 0) {
                    $notificationXml = $body->children()[0];
                    $notificationType = $notificationXml->getName();
                }
            }

            // Convert XML to array
            $notificationXmlString = $notificationXml->asXML();
            $jsonData = $this->client->xmlToArray($this->client->cleanSoapXml($notificationXmlString));

            // Save notification to file
            $notificationData = [
                'timestamp' => $timestamp->toIso8601String(),
                'notification_type' => $notificationType,
                'sales_channel_id' => $salesChannel->id,
                'sales_channel_name' => $salesChannel->name,
                'data' => $jsonData,
            ];

            $this->saveNotificationToFile($notificationType, $notificationData, $timestamp);

            Log::channel('ebay')->info("Notification received: {$notificationType}", [
                'timestamp' => $timestamp->toIso8601String(),
                'sales_channel_id' => $salesChannel->id,
                'file' => $this->getNotificationFileName($notificationType, $timestamp),
            ]);

            // Process order-related notifications
            if (EbayOrderService::isOrderNotification($notificationType)) {
                try {
                    $order = $this->orderService->processNotification($jsonData, $salesChannel, $notificationType, $timestamp);

                    if ($order) {
                        Log::channel('ebay')->info("Order processed from notification: {$notificationType}", [
                            'order_id' => $order->id,
                            'ebay_order_id' => $order->ebay_order_id,
                            'order_status' => $order->order_status,
                            'fulfillment_status' => $order->fulfillment_status,
                            'sales_channel_id' => $salesChannel->id,
                        ]);
                    } else {
                        Log::channel('ebay')->info("Order notification processed (no order returned): {$notificationType}", [
                            'sales_channel_id' => $salesChannel->id,
                        ]);
                    }
                } catch (Exception $orderException) {
                    Log::channel('ebay')->error("Failed to process order notification: {$notificationType}", [
                        'error' => $orderException->getMessage(),
                        'trace' => $orderException->getTraceAsString(),
                        'sales_channel_id' => $salesChannel->id,
                    ]);
                }
            }

            return response('OK', 200);
        } catch (Exception $e) {
            Log::channel('ebay')->error('eBay Platform Notification processing error', [
                'timestamp' => $timestamp->toIso8601String(),
                'sales_channel_id' => $salesChannel->id,
                'error' => $e->getMessage(),
            ]);

            // Save raw content even on error
            try {
                $notificationData = [
                    'timestamp' => $timestamp->toIso8601String(),
                    'notification_type' => 'EXCEPTION',
                    'sales_channel_id' => $salesChannel->id,
                    'sales_channel_name' => $salesChannel->name,
                    'error' => $e->getMessage(),
                    'raw_content' => $rawContent,
                ];
                $this->saveNotificationToFile('ERROR_NOTIFICATION', $notificationData, $timestamp);
            } catch (Exception $saveError) {
                // Ignore save errors
            }

            return response('OK', 200); // Still return OK to eBay to prevent retries
        }
    }

    /**
     * Save notification to individual JSON file
     */
    protected function saveNotificationToFile(string $notificationType, array $data, $timestamp): void
    {
        $directory = storage_path('logs/ebay/notifications/' . $timestamp->format('Y-m-d'));

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $this->getNotificationFileName($notificationType, $timestamp);
        $filepath = $directory . '/' . $filename;

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Generate notification filename with timestamp
     */
    protected function getNotificationFileName(string $notificationType, $timestamp): string
    {
        return $timestamp->format('H-i-s') . '_' . $notificationType . '.json';
    }

    // =========================================
    // WRAPPER METHODS FOR PRODUCTCONTROLLER
    // =========================================

    /**
     * Create an eBay listing for a product
     */
    public function createEbayListing(SalesChannel $channel, Product $product): array
    {
        try {
            $channel = $this->client->ensureValidToken($channel);

            $itemData = $this->prepareItemDataFromProduct($product, $channel);

            Log::info('Creating eBay listing', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'channel_id' => $channel->id,
            ]);

            $result = $this->ebayService->addFixedPriceItem($channel, $itemData);

            Log::info('eBay listing created', [
                'product_id' => $product->id,
                'result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to create eBay listing', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Revise/update an eBay listing for a product
     */
    public function reviseEbayItem(SalesChannel $channel, Product $product, string $itemId): array
    {
        try {
            $channel = $this->client->ensureValidToken($channel);

            $itemData = $this->prepareItemDataFromProduct($product, $channel);

            Log::info('Revising eBay listing', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'sku' => $product->sku,
                'channel_id' => $channel->id,
            ]);

            $result = $this->ebayService->reviseFixedPriceItem($channel, $itemId, $itemData);

            Log::info('eBay listing revised', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to revise eBay listing', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * End an eBay listing
     */
    public function endEbayItem(SalesChannel $channel, string $itemId, string $reason = 'NotAvailable'): array
    {
        try {
            $channel = $this->client->ensureValidToken($channel);

            Log::info('Ending eBay listing', [
                'item_id' => $itemId,
                'reason' => $reason,
                'channel_id' => $channel->id,
            ]);

            $result = $this->ebayService->endFixedPriceItem($channel, $itemId, $reason);

            Log::info('eBay listing ended', [
                'item_id' => $itemId,
                'result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to end eBay listing', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find an eBay listing by SKU
     */
    public function findEbayListingBySku(SalesChannel $channel, string $sku): ?array
    {
        try {
            $channel = $this->client->ensureValidToken($channel);

            Log::info('Finding eBay listing by SKU', [
                'sku' => $sku,
                'channel_id' => $channel->id,
            ]);

            $result = $this->ebayService->findListingBySku($channel, $sku);

            Log::info('eBay listing search result', [
                'sku' => $sku,
                'found' => $result !== null,
                'result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to find eBay listing by SKU', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Sync inventory (quantity only) to eBay
     * Uses ReviseInventoryStatus API for optimized inventory updates
     */
    public function syncInventory(SalesChannel $channel, string $itemId, Product $product): array
    {
        try {
            $channel = $this->client->ensureValidToken($channel);

            // Calculate total quantity from product_stocks table
            $totalQuantity = ProductStock::where('product_id', $product->id)
                ->where('active_status', '1')
                ->where('delete_status', '0')
                ->sum(DB::raw('CAST(quantity AS UNSIGNED)'));

            $quantity = (int) $totalQuantity;

            // Debug: Log stock details
            $stockRecords = ProductStock::where('product_id', $product->id)->get();
            Log::info('Product stock records for sync', [
                'product_id' => $product->id,
                'stock_count' => $stockRecords->count(),
                'stocks' => $stockRecords->map(fn($s) => [
                    'id' => $s->id,
                    'quantity' => $s->quantity,
                    'active_status' => $s->active_status,
                    'delete_status' => $s->delete_status,
                ])->toArray(),
                'calculated_total' => $quantity,
            ]);

            Log::info('Syncing inventory to eBay', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'sku' => $product->sku,
                'quantity' => $quantity,
                'channel_id' => $channel->id,
            ]);

            $result = $this->ebayService->reviseInventoryStatus($channel, $itemId, $quantity);

            Log::info('eBay inventory sync result', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to sync inventory to eBay', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync product data to eBay (quantity, weight, dimensions, and optionally SKU).
     *
     * Used when updating a product locally with sales channel checkbox checked.
     * Pushes: quantity, weight, length, width, height, and SKU (if changed).
     */
    public function syncProductToEbay(SalesChannel $channel, string $itemId, Product $product, bool $syncSku = false): array
    {
        try {
            $channel = $this->client->ensureValidToken($channel);

            // Calculate total quantity from product_stocks table
            $totalQuantity = ProductStock::where('product_id', $product->id)
                ->where('active_status', '1')
                ->where('delete_status', '0')
                ->sum(DB::raw('CAST(quantity AS UNSIGNED)'));

            // Get product meta for dimensions (use query to avoid serialization issues)
            $meta = $product->product_meta()->pluck('meta_value', 'meta_key')->toArray();

            $fields = [
                'quantity'       => (int) $totalQuantity,
                'weight'         => (float) ($meta['weight'] ?? 0),
                'weight_unit'    => $meta['weight_unit'] ?? 'lbs',
                'length'         => (float) ($meta['length'] ?? 0),
                'width'          => (float) ($meta['width'] ?? 0),
                'height'         => (float) ($meta['height'] ?? 0),
                'dimension_unit' => $meta['dimension_unit'] ?? 'inches',
            ];

            // Add SKU if changed
            if ($syncSku) {
                $fields['sku'] = $product->sku;
            }

            Log::info('Syncing product to eBay', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'sku' => $product->sku,
                'sync_sku' => $syncSku,
                'fields' => $fields,
                'channel_id' => $channel->id,
            ]);

            // Use reviseItem to update quantity, dimensions, and optionally SKU
            $result = $this->ebayService->reviseItem($channel, $itemId, $fields);

            Log::info('eBay product sync result', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to sync product to eBay', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mark an eBay order as shipped with tracking information
     */
    public function markOrderAsShipped(
        SalesChannel $channel,
        string $ebayOrderId,
        string $shippingCarrier,
        string $trackingNumber,
        ?string $itemId = null,
        ?string $transactionId = null
    ): array {
        try {
            $channel = $this->client->ensureValidToken($channel);

            Log::info('Marking order as shipped on eBay', [
                'ebay_order_id' => $ebayOrderId,
                'shipping_carrier' => $shippingCarrier,
                'tracking_number' => $trackingNumber,
                'channel_id' => $channel->id,
            ]);

            // Use OrderID if available (preferred for multi-item orders)
            if (!empty($ebayOrderId)) {
                $result = $this->ebayService->completeSaleByOrderId(
                    $channel,
                    $ebayOrderId,
                    $shippingCarrier,
                    $trackingNumber,
                    true
                );
            } elseif (!empty($itemId) && !empty($transactionId)) {
                // Fallback to ItemID + TransactionID for single item orders
                $result = $this->ebayService->completeSale(
                    $channel,
                    $itemId,
                    $transactionId,
                    $shippingCarrier,
                    $trackingNumber,
                    true
                );
            } else {
                return [
                    'success' => false,
                    'message' => 'Either ebay_order_id or itemId+transactionId is required',
                ];
            }

            Log::info('eBay mark as shipped result', [
                'ebay_order_id' => $ebayOrderId,
                'result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to mark order as shipped on eBay', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Relist an ended eBay item
     */
    public function relistEbayItem(SalesChannel $channel, Product $product, string $itemId): array
    {
        try {
            $channel = $this->client->ensureValidToken($channel);

            $itemData = $this->prepareItemDataFromProduct($product, $channel);

            Log::info('Relisting eBay item', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'channel_id' => $channel->id,
            ]);

            $result = $this->ebayService->relistFixedPriceItem($channel, $itemId, $itemData);

            Log::info('eBay item relisted', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to relist eBay item', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // HELPERS
    // =========================================

    /**
     * Prepare item data array from a Product model for eBay API
     */
    private function prepareItemDataFromProduct(Product $product, SalesChannel $channel): array
    {
        $productMeta = $product->product_meta;

        $price = $productMeta['sale_price'] ?? $productMeta['regular_price'] ?? $product->price ?? 0;

        $description = $product->description;
        if (empty($description)) {
            $description = $product->short_description;
        }
        if (empty($description)) {
            $description = $product->name;
        }

        $totalQuantity = $product->product_stocks()
            ->where('active_status', 1)
            ->where('delete_status', 0)
            ->sum('quantity');

        $itemData = [
            'description' => $description,
            'sku' => $product->sku,
            'quantity' => (int) $totalQuantity ?: 0,
            'condition_id' => 1000,
            'listing_duration' => 'GTC',
            'currency' => 'USD',
            'country' => 'US',
            'location' => 'United States',
        ];

        if (!empty($productMeta['weight'])) {
            $itemData['weight'] = $productMeta['weight'];
        }
        if (!empty($productMeta['length'])) {
            $itemData['length'] = $productMeta['length'];
        }
        if (!empty($productMeta['width'])) {
            $itemData['width'] = $productMeta['width'];
        }
        if (!empty($productMeta['height'])) {
            $itemData['height'] = $productMeta['height'];
        }

        if ($product->category_id) {
            $itemData['category_id'] = $this->getEbayCategoryId($product->category_id);
        }

        if (!empty($product->product_image)) {
            $itemData['image_url'] = asset('storage/' . $product->product_image);
        }

        return $itemData;
    }

    /**
     * Get eBay category ID from local category ID
     */
    private function getEbayCategoryId(int $localCategoryId): string
    {
        $categoryMapping = [
            // localCategoryId => ebayCategoryId
        ];

        return $categoryMapping[$localCategoryId] ?? '99';
    }

    /**
     * Return error response
     */
    private function errorResponse(string $message, int $status = 500)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
