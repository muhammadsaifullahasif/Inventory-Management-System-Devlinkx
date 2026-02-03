<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Rack;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\OrderMeta;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use App\Models\ProductStock;
use App\Models\SalesChannel;
use Illuminate\Http\Request;
use App\Services\EbayService;
use App\Models\EbayImportLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Jobs\ImportEbayListingsJob;
use App\Jobs\SyncEbayOrdersJob;
use App\Http\Controllers\OrderController;

class EbayController extends Controller
{
    private const EBAY_API_URL = 'https://api.ebay.com/ws/api.dll';
    private const EBAY_TOKEN_URL = 'https://api.ebay.com/identity/v1/oauth2/token';
    private const API_COMPATIBILITY_LEVEL = '967';
    private const API_SITE_ID = '0'; // US
    private const BATCH_SIZE = 50; // Process 50 items per job

    public function __construct(protected EbayService $ebayService) {}

    /**
     * Get ALL active listings
     */
    public function getAllActiveListings(string $id)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            $allItems = [];
            $page = 1;
            $perPage = 100;

            Log::info('Starting eBay listings fetch', ['sales_channel_id' => $salesChannel->id]);

            // Fetch all items from eBay
            do {
                $endTimeFrom = gmdate('Y-m-d\TH:i:s\Z');
                $endTimeTo = gmdate('Y-m-d\TH:i:s\Z', strtotime('+120 days'));
                $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                    <GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <ErrorLanguage>en_US</ErrorLanguage>
                        <WarningLevel>High</WarningLevel>
                        <DetailLevel>ReturnAll</DetailLevel>
                        <EndTimeFrom>' . $endTimeFrom . '</EndTimeFrom>
                        <EndTimeTo>' . $endTimeTo . '</EndTimeTo>
                        <IncludeWatchCount>true</IncludeWatchCount>
                        <Pagination>
                            <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                            <PageNumber>' . $page . '</PageNumber>
                        </Pagination>
                        <GranularityLevel>Fine</GranularityLevel>
                    </GetSellerListRequest>';
                $rawResponse = $this->callTradingApi($salesChannel, 'GetSellerList', $xmlRequest);

                // Log raw XML response for debugging (first page only to avoid huge logs)
                if ($page === 1) {
                    Log::channel('ebay')->debug('eBay GetSellerList Raw XML Response (Page 1)', [
                        'sales_channel_id' => $salesChannel->id,
                        'raw_response_length' => strlen($rawResponse),
                        'raw_response_preview' => substr($rawResponse, 0, 5000), // First 5000 chars
                    ]);
                }

                $response = $this->parseActiveListingsResponse($rawResponse);

                // Log parsed items for debugging
                Log::channel('ebay')->debug('eBay Parsed Listings - Page ' . $page, [
                    'page' => $page,
                    'items_count' => count($response['items']),
                    'items' => $response['items'], // Full listing data
                ]);

                $allItems = array_merge($allItems, $response['items']);
                $totalPages = $response['pagination']['totalPages'];

                Log::info('Fetched eBay page', [
                    'page' => $page,
                    'total_pages' => $totalPages,
                    'items_on_page' => count($response['items']),
                ]);
                
                $page++;
            } while ($page <= $totalPages);

            $totalListings = count($allItems);

            // Log all fetched listings to dedicated eBay log channel for debugging
            Log::channel('ebay')->info('eBay Import - All Fetched Listings', [
                'timestamp' => now()->toIso8601String(),
                'sales_channel_id' => $salesChannel->id,
                'total_listings' => $totalListings,
            ]);

            // Log each listing separately for easier reading in the log file
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

            if ($totalListings === 0) {
                return redirect()->back()->with('info', 'No active listings found to import.');
            }

            // Create import log
            $importLog = EbayImportLog::create([
                'sales_channel_id' => $id,
                'total_listings' => $totalListings,
                'total_batcheds' => 0, // Will be updated below
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

            // Update import log with total batches
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
                ->delay(now()->addSeconds($batchNumber * 2)); // Stagger jobs by 2 seconds
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

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by sales channel if provided
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

    /**
     * Get ALL active listings synchronously (original method)
     * Kept for backward compatibility or manual sync if needed
     */
    public function getAllActiveListingsSync(string $id)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            $allItems = [];
            $page = 1;
            $perPage = 100;

            do {
                $endTimeFrom = gmdate('Y-m-d\TH:i:s\Z');
                $endTimeTo = gmdate('Y-m-d\TH:i:s\Z', strtotime('+120 days'));
                $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                    <GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <ErrorLanguage>en_US</ErrorLanguage>
                        <WarningLevel>High</WarningLevel>
                        <DetailLevel>ReturnAll</DetailLevel>
                        <EndTimeFrom>' . $endTimeFrom . '</EndTimeFrom>
                        <EndTimeTo>' . $endTimeTo . '</EndTimeTo>
                        <IncludeWatchCount>true</IncludeWatchCount>
                        <Pagination>
                            <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                            <PageNumber>' . $page . '</PageNumber>
                        </Pagination>
                        <GranularityLevel>Fine</GranularityLevel>
                    </GetSellerListRequest>';
                $response = $this->callTradingApi($salesChannel, 'GetSellerList', $xmlRequest);
                $response =  $this->parseActiveListingsResponse($response);
                
                $allItems = array_merge($allItems, $response['items']);
                $totalPages = $response['pagination']['totalPages'];
                $page++;
            } while ($page <= $totalPages);

            // Set PHP max execution time to 10 minutes
            set_time_limit(1200);

            // Tracking counters
            $totalListings = count($allItems);
            $insertedCount = 0;
            $updatedCount = 0;
            $errorCount = 0;
            $errors = [];

            // Get default warehouse and rack once (outside the loop for efficiency)
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

                    // Update product meta (include all the meta fields from original)
                    // ... (truncated for brevity - include all meta updates from original code)

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

    /**
     * Get single item with full details including original/regular price
     */
    public function getItemDetails(string $id, string $itemId)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                    <ErrorLanguage>en_US</ErrorLanguage>
                    <WarningLevel>High</WarningLevel>
                    <DetailLevel>ReturnAll</DetailLevel>
                    <ItemID>' . $itemId . '</ItemID>
                    <IncludeItemSpecifics>true</IncludeItemSpecifics>
                    <IncludeWatchCount>true</IncludeWatchCount>
                </GetItemRequest>';

            $response = $this->callTradingApi($salesChannel, 'GetItem', $xmlRequest);
            $xml = simplexml_load_string($response);
            $this->checkForErrors($xml);

            $item = $this->parseItem($xml->Item, true);

            return response()->json([
                'success' => true,
                'item' => $item,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Call eBay Trading API
     */
    private function callTradingApi(SalesChannel $salesChannel, string $callName, string $xmlRequest): string
    {
        $response = Http::timeout(300) // Increased to 5 minutes
            ->connectTimeout(60) // Increased to 60 seconds
            ->withHeaders([
                'X-EBAY-API-SITEID' => self::API_SITE_ID,
                'X-EBAY-API-COMPATIBILITY-LEVEL' => self::API_COMPATIBILITY_LEVEL,
                'X-EBAY-API-CALL-NAME' => $callName,
                'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                'Content-Type' => 'text/xml',
            ])
            ->withBody($xmlRequest, 'text/xml')
            ->post(self::EBAY_API_URL);

        if ($response->failed()) {
            Log::error("eBay {$callName} Failed", [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw new Exception("eBay {$callName} failed: " . $response->body());
        }

        return $response->body();
    }

    /**
     * Parse active listings response
     */
    private function parseActiveListingsResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);
        $this->checkForErrors($xml);

        $result = [
            'success' => true,
            'items' => [],
            'pagination' => [
                'totalEntries' => (int) ($xml->PaginationResult->TotalNumberOfEntries ?? 0),
                'totalPages' => (int) ($xml->PaginationResult->TotalNumberOfPages ?? 0),
                'pageNumber' => (int) ($xml->PageNumber ?? 1),
            ],
        ];

        if (isset($xml->ItemArray->Item)) {
            foreach ($xml->ItemArray->Item as $item) {
                $result['items'][] = $this->parseItem($item, true);
            }
        }

        $result['total_items'] = count($result['items']);
        return $result;
    }

    /**
     * Parse single item from XML
     */
    private function parseItem($item, bool $includeFullDetails = false): array
    {
        // Get current selling price
        $currentPrice = (float) ($item->SellingStatus->CurrentPrice ?? $item->BuyItNowPrice ?? $item->StartPrice ?? 0);
        $currency = (string) ($item->SellingStatus->CurrentPrice['currencyID'] ?? 'USD');

        // Get StartPrice (original listing price)
        $startPrice = (float) ($item->StartPrice ?? 0);

        // Check for strike-through price (original price when item is on sale)
        // eBay uses DiscountPriceInfo for sale pricing
        $regularPrice = $currentPrice;
        $salePrice = null;
        $isOnSale = false;

        // Method 1: Check DiscountPriceInfo (most reliable for sale prices)
        if (isset($item->DiscountPriceInfo)) {
            // OriginalRetailPrice is the strike-through price (regular price)
            if (isset($item->DiscountPriceInfo->OriginalRetailPrice)) {
                $regularPrice = (float) $item->DiscountPriceInfo->OriginalRetailPrice;
                $salePrice = $currentPrice;
                $isOnSale = true;
            }
            // MinimumAdvertisedPrice for MAP pricing
            if (isset($item->DiscountPriceInfo->MinimumAdvertisedPrice)) {
                $regularPrice = (float) $item->DiscountPriceInfo->MinimumAdvertisedPrice;
                $salePrice = $currentPrice;
                $isOnSale = true;
            }
            // SoldOneBay and SoldOffeBay pricing
            if (isset($item->DiscountPriceInfo->PricingTreatment)) {
                $pricingTreatment = (string) $item->DiscountPriceInfo->PricingTreatment;
                if ($pricingTreatment === 'STP' || $pricingTreatment === 'MAP') {
                    $isOnSale = true;
                }
            }
        }

        // Method 2: Check ListingDetails for promotional sale
        if (!$isOnSale && isset($item->ListingDetails->StartPrice)) {
            $listingStartPrice = (float) $item->ListingDetails->StartPrice;
            if ($listingStartPrice > $currentPrice) {
                $regularPrice = $listingStartPrice;
                $salePrice = $currentPrice;
                $isOnSale = true;
            }
        }

        // Method 3: Check SellingStatus for PromotionalSaleDetails
        if (!$isOnSale && isset($item->SellingStatus->PromotionalSaleDetails)) {
            $promoDetails = $item->SellingStatus->PromotionalSaleDetails;
            if (isset($promoDetails->OriginalPrice)) {
                $regularPrice = (float) $promoDetails->OriginalPrice;
                $salePrice = $currentPrice;
                $isOnSale = true;
            }
        }

        // Method 4: Compare StartPrice with CurrentPrice
        if (!$isOnSale && $startPrice > 0 && $startPrice > $currentPrice) {
            $regularPrice = $startPrice;
            $salePrice = $currentPrice;
            $isOnSale = true;
        }

        $parsed = [
            'item_id' => (string) $item->ItemID,
            'title' => (string) $item->Title,
            'sku' => (string) ($item->SKU ?? ''),
            'price' => [
                'value' => $currentPrice,
                'currency' => $currency,
            ],
            'regular_price' => [
                'value' => $regularPrice,
                'currency' => $currency,
            ],
            'sale_price' => $salePrice !== null ? [
                'value' => $salePrice,
                'currency' => $currency,
            ] : null,
            'is_on_sale' => $isOnSale,
            'start_price' => (float) ($item->StartPrice ?? 0),
            'buy_it_now_price' => (float) ($item->BuyItNowPrice ?? 0),
            'reserve_price' => (float) ($item->ReservePrice ?? 0),
            'quantity' => (int) ($item->Quantity ?? 0),
            'quantity_available' => (int) ($item->QuantityAvailable ?? 0),
            'quantity_sold' => (int) ($item->SellingStatus->QuantitySold ?? 0),
            'condition' => (string) ($item->ConditionDisplayName ?? ''),
            'condition_id' => (string) ($item->ConditionID ?? ''),
            'category' => [
                'id' => (string) ($item->PrimaryCategory->CategoryID ?? ''),
                'name' => (string) ($item->PrimaryCategory->CategoryName ?? ''),
            ],
            'listing_type' => (string) ($item->ListingType ?? ''),
            'listing_status' => (string) ($item->SellingStatus->ListingStatus ?? ''),
            'listing_url' => (string) ($item->ListingDetails->ViewItemURL ?? ''),
            'start_time' => (string) ($item->ListingDetails->StartTime ?? ''),
            'end_time' => (string) ($item->ListingDetails->EndTime ?? ''),
            'images' => $this->parseImages($item),
            'dimensions' => $this->parseDimensions($item),
        ];

        if ($includeFullDetails) {
            $parsed['description'] = (string) ($item->Description ?? '');
            $parsed['location'] = (string) ($item->Location ?? '');
            $parsed['country'] = (string) ($item->Country ?? '');
            $parsed['watch_count'] = (int) ($item->WatchCount ?? 0);
            $parsed['item_specifics'] = $this->parseItemSpecifics($item);
            $parsed['variations'] = $this->parseVariations($item);
            $parsed['shipping_details'] = $this->parseShippingDetails($item);
            $parsed['return_policy'] = $this->parseReturnPolicy($item);
        }

        return $parsed;
    }

    /**
     * Parse item dimensions from ShippingPackageDetails
     */
    private function parseDimensions($item): array
    {
        $dimensions = [
            'weight' => null,
            'weight_unit' => null,
            'length' => null,
            'width' => null,
            'height' => null,
            'dimension_unit' => null,
        ];

        // Check ShippingPackageDetails for package dimensions
        if (isset($item->ShippingPackageDetails)) {
            $pkg = $item->ShippingPackageDetails;

            // Weight
            if (isset($pkg->WeightMajor)) {
                $weightMajor = (float) $pkg->WeightMajor;
                $weightMinor = (float) ($pkg->WeightMinor ?? 0);
                $dimensions['weight'] = $weightMajor + ($weightMinor / 16); // Convert oz to lbs
                $dimensions['weight_unit'] = (string) ($pkg->WeightMajor['unit'] ?? 'lbs');
            }

            // Dimensions
            if (isset($pkg->PackageDepth)) {
                $dimensions['length'] = (float) $pkg->PackageDepth;
                $dimensions['dimension_unit'] = (string) ($pkg->PackageDepth['unit'] ?? 'inches');
            }
            if (isset($pkg->PackageWidth)) {
                $dimensions['width'] = (float) $pkg->PackageWidth;
            }
            if (isset($pkg->PackageLength)) {
                $dimensions['height'] = (float) $pkg->PackageLength;
            }
        }

        // Also check ShippingDetails for calculated shipping weight
        if (isset($item->ShippingDetails->CalculatedShippingRate)) {
            $calc = $item->ShippingDetails->CalculatedShippingRate;

            if ($dimensions['weight'] === null && isset($calc->WeightMajor)) {
                $weightMajor = (float) $calc->WeightMajor;
                $weightMinor = (float) ($calc->WeightMinor ?? 0);
                $dimensions['weight'] = $weightMajor + ($weightMinor / 16);
                $dimensions['weight_unit'] = (string) ($calc->WeightMajor['unit'] ?? 'lbs');
            }

            if ($dimensions['length'] === null && isset($calc->PackageDepth)) {
                $dimensions['length'] = (float) $calc->PackageDepth;
                $dimensions['dimension_unit'] = (string) ($calc->PackageDepth['unit'] ?? 'inches');
            }
            if ($dimensions['width'] === null && isset($calc->PackageWidth)) {
                $dimensions['width'] = (float) $calc->PackageWidth;
            }
            if ($dimensions['height'] === null && isset($calc->PackageLength)) {
                $dimensions['height'] = (float) $calc->PackageLength;
            }
        }

        return $dimensions;
    }

    /**
     * Parse images from item
     */
    private function parseImages($item): array
    {
        $images = [];
        if (isset($item->PictureDetails->PictureURL)) {
            foreach ($item->PictureDetails->PictureURL as $url) {
                $images[] = (string) $url;
            }
        }
        return $images;
    }

    /**
     * Parse item specifics
     */
    private function parseItemSpecifics($item): array
    {
        $specifics = [];
        if (isset($item->ItemSpecifics->NameValueList)) {
            foreach ($item->ItemSpecifics->NameValueList as $spec) {
                $name = (string) $spec->Name;
                $values = [];
                if (isset($spec->Value)) {
                    foreach ($spec->Value as $val) {
                        $values[] = (string) $val;
                    }
                }
                $specifics[$name] = count($values) === 1 ? $values[0] : $values;
            }
        }
        return $specifics;
    }

    /**
     * Parse variations
     */
    private function parseVariations($item): array
    {
        $variations = [];
        if (isset($item->Variations->Variation)) {
            foreach ($item->Variations->Variation as $var) {
                $variation = [
                    'sku' => (string) ($var->SKU ?? ''),
                    'quantity' => (int) ($var->Quantity ?? 0),
                    'quantity_sold' => (int) ($var->SellingStatus->QuantitySold ?? 0),
                    'price' => (float) ($var->StartPrice ?? 0),
                    'specifics' => [],
                ];
                if (isset($var->VariationSpecifics->NameValueList)) {
                    foreach ($var->VariationSpecifics->NameValueList as $spec) {
                        $variation['specifics'][(string) $spec->Name] = (string) ($spec->Value ?? '');
                    }
                }
                $variations[] = $variation;
            }
        }
        return $variations;
    }

    /**
     * Parse shipping details
     */
    private function parseShippingDetails($item): array
    {
        if (!isset($item->ShippingDetails)) {
            return [];
        }

        $details = [
            'shipping_type' => (string) ($item->ShippingDetails->ShippingType ?? ''),
            'global_shipping' => ((string) ($item->ShippingDetails->GlobalShipping ?? 'false')) === 'true',
            'services' => [],
        ];

        if (isset($item->ShippingDetails->ShippingServiceOptions)) {
            foreach ($item->ShippingDetails->ShippingServiceOptions as $svc) {
                $details['services'][] = [
                    'service' => (string) ($svc->ShippingService ?? ''),
                    'cost' => (float) ($svc->ShippingServiceCost ?? 0),
                    'free_shipping' => ((string) ($svc->FreeShipping ?? 'false')) === 'true',
                ];
            }
        }

        return $details;
    }

    /**
     * Parse return policy
     */
    private function parseReturnPolicy($item): array
    {
        if (!isset($item->ReturnPolicy)) {
            return [];
        }

        return [
            'returns_accepted' => (string) ($item->ReturnPolicy->ReturnsAcceptedOption ?? ''),
            'returns_within' => (string) ($item->ReturnPolicy->ReturnsWithinOption ?? ''),
            'refund' => (string) ($item->ReturnPolicy->RefundOption ?? ''),
            'shipping_cost_paid_by' => (string) ($item->ReturnPolicy->ShippingCostPaidByOption ?? ''),
        ];
    }

    /**
     * Check for API errors in response
     */
    private function checkForErrors($xml): void
    {
        if ($xml === false) {
            throw new Exception('Failed to parse eBay XML response');
        }

        if ((string) $xml->Ack === 'Failure') {
            $short = (string) ($xml->Errors->ShortMessage ?? 'Unknown error');
            $long = (string) ($xml->Errors->LongMessage ?? '');
            throw new Exception("eBay API Error: {$short} - {$long}");
        }
    }

    /**
     * Get sales channel with valid token (refresh if needed)
     */
    private function getSalesChannelWithValidToken(string $id): SalesChannel
    {
        $salesChannel = SalesChannel::findOrFail($id);

        if ($this->isAccessTokenExpired($salesChannel)) {
            $salesChannel = $this->refreshAccessToken($salesChannel);
        }

        return $salesChannel;
    }

    /**
     * Check if access token is expired
     */
    private function isAccessTokenExpired(SalesChannel $salesChannel): bool
    {
        if (empty($salesChannel->access_token) || empty($salesChannel->access_token_expires_at)) {
            return true;
        }

        return now()->addMinutes(5)->greaterThanOrEqualTo($salesChannel->access_token_expires_at);
    }

    /**
     * Refresh access token
     */
    private function refreshAccessToken(SalesChannel $salesChannel): SalesChannel
    {
        if (empty($salesChannel->refresh_token)) {
            throw new \Exception('No refresh token available. Please re-authorize with eBay.');
        }

        if ($salesChannel->refresh_token_expires_at && now()->greaterThanOrEqualTo($salesChannel->refresh_token_expires_at)) {
            throw new \Exception('Refresh token has expired. Please re-authorize with eBay.');
        }

        $tokenData = $this->refreshUserToken($salesChannel);

        $salesChannel->access_token = $tokenData['access_token'];
        $salesChannel->access_token_expires_at = now()->addSeconds($tokenData['expires_in']);

        if (isset($tokenData['refresh_token'])) {
            $salesChannel->refresh_token = $tokenData['refresh_token'];
            if (isset($tokenData['refresh_token_expires_in'])) {
                $salesChannel->refresh_token_expires_at = now()->addSeconds($tokenData['refresh_token_expires_in']);
            }
        }

        $salesChannel->save();

        Log::info('eBay access token refreshed', [
            'sales_channel_id' => $salesChannel->id,
            'new_expires_at' => $salesChannel->access_token_expires_at,
        ]);

        return $salesChannel;
    }

    /**
     * Refresh user access token
     */
    public function refreshUserToken(SalesChannel $salesChannel): array
    {
        $response = Http::timeout(60)
            ->connectTimeout(30)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($salesChannel->client_id . ':' . $salesChannel->client_secret),
            ])
            ->asForm()
            ->post(self::EBAY_TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $salesChannel->refresh_token,
                'scope' => $salesChannel->user_scopes,
            ]);

        if ($response->failed()) {
            Log::error('eBay Refresh Token Failed', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw new Exception('eBay refresh token failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Update an eBay listing using item_id (ReviseItem API)
     *
     * Supported fields: title, description, price, quantity, sku
     */
    public function updateListing(array $data, string $id, string $itemId, bool $returnArray = false)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            // Build the ReviseItem XML request
            $xmlParts = [];

            // Title
            if (isset($data['title']) && !empty($data['title'])) {
                $xmlParts[] = '<Title>' . htmlspecialchars($data['title']) . '</Title>';
            }

            // Description
            if (isset($data['description'])) {
                $xmlParts[] = '<Description><![CDATA[' . $data['description'] . ']]></Description>';
            }

            // Price (StartPrice for Buy It Now / Fixed Price)
            if (isset($data['price'])) {
                $currency = $data['currency'] ?? 'USD';
                $xmlParts[] = '<StartPrice currencyID="' . $currency . '">' . $data['price'] . '</StartPrice>';
            }

            // Quantity
            if (isset($data['quantity'])) {
                $xmlParts[] = '<Quantity>' . (int) $data['quantity'] . '</Quantity>';
            }

            // SKU
            if (isset($data['sku'])) {
                $xmlParts[] = '<SKU>' . htmlspecialchars($data['sku']) . '</SKU>';
            }

            // Condition
            if (isset($data['condition_id'])) {
                $xmlParts[] = '<ConditionID>' . (int) $data['condition_id'] . '</ConditionID>';
            }

            if (empty($xmlParts)) {
                $error = ['success' => false, 'message' => 'No fields provided to update'];
                return $returnArray ? $error : $this->errorResponse('No fields provided to update', 400);
            }

            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                    <ErrorLanguage>en_US</ErrorLanguage>
                    <WarningLevel>High</WarningLevel>
                    <Item>
                        <ItemID>' . $itemId . '</ItemID>
                        ' . implode("\n                        ", $xmlParts) . '
                    </Item>
                </ReviseItemRequest>';

            Log::info('eBay ReviseItem Request', [
                'item_id' => $itemId,
                'data' => $data,
                'xml' => $xmlRequest,
            ]);

            $response = $this->callTradingApi($salesChannel, 'ReviseItem', $xmlRequest);

            Log::info('eBay ReviseItem Response', [
                'item_id' => $itemId,
                'response' => $response,
            ]);

            $result = $this->parseReviseItemResponse($response);

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
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            $quantity = (int) $request->input('quantity', 0);

            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                    <ErrorLanguage>en_US</ErrorLanguage>
                    <WarningLevel>High</WarningLevel>
                    <Item>
                        <ItemID>' . $itemId . '</ItemID>
                        <Quantity>' . $quantity . '</Quantity>
                    </Item>
                </ReviseItemRequest>';

            $response = $this->callTradingApi($salesChannel, 'ReviseItem', $xmlRequest);
            $result = $this->parseReviseItemResponse($response);

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
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            $price = $request->input('price');
            $currency = $request->input('currency', 'USD');

            if (!$price) {
                return $this->errorResponse('Price is required', 400);
            }

            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                    <ErrorLanguage>en_US</ErrorLanguage>
                    <WarningLevel>High</WarningLevel>
                    <Item>
                        <ItemID>' . $itemId . '</ItemID>
                        <StartPrice currencyID="' . $currency . '">' . $price . '</StartPrice>
                    </Item>
                </ReviseItemRequest>';

            $response = $this->callTradingApi($salesChannel, 'ReviseItem', $xmlRequest);
            $result = $this->parseReviseItemResponse($response);

            return response()->json($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Parse ReviseItem response
     */
    private function parseReviseItemResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);
        $this->checkForErrors($xml);

        $result = [
            'success' => true,
            'item_id' => (string) ($xml->ItemID ?? ''),
            'ack' => (string) $xml->Ack,
            'fees' => [],
        ];

        // Parse listing fees if any
        if (isset($xml->Fees->Fee)) {
            foreach ($xml->Fees->Fee as $fee) {
                $result['fees'][] = [
                    'name' => (string) $fee->Name,
                    'fee' => (float) $fee->Fee,
                    'currency' => (string) ($fee->Fee['currencyID'] ?? 'USD'),
                ];
            }
        }

        // Check for warnings
        if (isset($xml->Errors) && (string) $xml->Ack === 'Warning') {
            $result['warnings'] = [];
            foreach ($xml->Errors as $error) {
                if ((string) $error->SeverityCode === 'Warning') {
                    $result['warnings'][] = [
                        'code' => (string) $error->ErrorCode,
                        'message' => (string) $error->ShortMessage,
                        'long_message' => (string) $error->LongMessage,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Sync orders from eBay
     * Fetches recent orders and creates them in the local database
     */
    public function syncOrders(string $id)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            // Fetch orders from the last 90 days by default
            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime('-90 days'));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');

            Log::info('Starting eBay order sync', [
                'sales_channel_id' => $id,
                'from' => $createTimeFrom,
                'to' => $createTimeTo,
            ]);

            $allOrders = [];
            $page = 1;
            $perPage = 500;

            // Fetch all orders with pagination
            do {
                $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                    <GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <ErrorLanguage>en_US</ErrorLanguage>
                        <WarningLevel>High</WarningLevel>
                        <DetailLevel>ReturnAll</DetailLevel>
                        <CreateTimeFrom>' . $createTimeFrom . '</CreateTimeFrom>
                        <CreateTimeTo>' . $createTimeTo . '</CreateTimeTo>
                        <OrderRole>Seller</OrderRole>
                        <OrderStatus>All</OrderStatus>
                        <Pagination>
                            <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                            <PageNumber>' . $page . '</PageNumber>
                        </Pagination>
                    </GetOrdersRequest>';

                $response = $this->callTradingApi($salesChannel, 'GetOrders', $xmlRequest);
                $result = $this->parseOrdersResponse($response);

                $allOrders = array_merge($allOrders, $result['orders']);
                $totalPages = $result['pagination']['totalPages'];

                Log::info('Fetched eBay orders page', [
                    'page' => $page,
                    'total_pages' => $totalPages,
                    'orders_on_page' => count($result['orders']),
                ]);

                $page++;
            } while ($page <= $totalPages);

            $totalOrders = count($allOrders);

            if ($totalOrders === 0) {
                return redirect()->back()->with('info', 'No orders found to sync.');
            }

            // Process orders
            $syncedCount = 0;
            $updatedCount = 0;
            $errorCount = 0;

            // foreach ($allOrders as $ebayOrder) {
            //     try {
            //         $result = $this->processEbayOrder($ebayOrder, $id);
            //         if ($result === 'created') {
            //             $syncedCount++;
            //         } elseif ($result === 'updated') {
            //             $updatedCount++;
            //         }
            //     } catch (Exception $e) {
            //         $errorCount++;
            //         Log::error('Failed to process eBay order', [
            //             'order_id' => $ebayOrder['order_id'] ?? 'unknown',
            //             'error' => $e->getMessage(),
            //         ]);
            //     }
            // }

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

            return $totalOrders;

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
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            // Dispatch job to sync orders
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
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            $daysBack = $request->input('days', 90);
            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime("-90 days"));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');

            $page = 1;
            $perPage = 500;

            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                <GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                    <ErrorLanguage>en_US</ErrorLanguage>
                    <WarningLevel>High</WarningLevel>
                    <DetailLevel>ReturnAll</DetailLevel>
                    <CreateTimeFrom>' . $createTimeFrom . '</CreateTimeFrom>
                    <CreateTimeTo>' . $createTimeTo . '</CreateTimeTo>
                    <OrderRole>Seller</OrderRole>
                    <OrderStatus>All</OrderStatus>
                    <Pagination>
                        <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                        <PageNumber>' . $page . '</PageNumber>
                    </Pagination>
                </GetOrdersRequest>';

            $response = $this->callTradingApi($salesChannel, 'GetOrders', $xmlRequest);
            $result = $this->parseOrdersResponse($response);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Parse GetOrders response
     */
    private function parseOrdersResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);
        $this->checkForErrors($xml);

        $result = [
            'orders' => [],
            'pagination' => [
                'totalEntries' => (int) ($xml->PaginationResult->TotalNumberOfEntries ?? 0),
                'totalPages' => (int) ($xml->PaginationResult->TotalNumberOfPages ?? 0),
                'pageNumber' => (int) ($xml->PageNumber ?? 1),
            ],
        ];

        if (isset($xml->OrderArray->Order)) {
            foreach ($xml->OrderArray->Order as $order) {
                $result['orders'][] = $this->parseOrder($order);
            }
        }

        return $result;
    }

    /**
     * Parse single order from XML
     */
    private function parseOrder($order): array
    {
        // Get first transaction to extract buyer details
        $firstTransaction = $order->TransactionArray->Transaction[0] ?? null;
        $buyerNode = $firstTransaction->Buyer ?? null;

        // Parse buyer shipping address
        $shippingAddress = [];
        if (isset($order->ShippingAddress)) {
            $addr = $order->ShippingAddress;
            $shippingAddress = [
                'name' => (string) ($addr->Name ?? ''),
                'street_1' => (string) ($addr->Street1 ?? ''),
                'street_2' => (string) ($addr->Street2 ?? ''),
                'city_name' => (string) ($addr->CityName ?? ''),
                'state_or_province' => (string) ($addr->StateOrProvince ?? ''),
                'country' => (string) ($addr->Country ?? ''),
                'country_name' => (string) ($addr->CountryName ?? ''),
                'phone' => (string) ($addr->Phone ?? ''),
                'postal_code' => (string) ($addr->PostalCode ?? ''),
            ];
        }

        // Parse line items with all details
        $lineItems = [];
        if (isset($order->TransactionArray->Transaction)) {
            foreach ($order->TransactionArray->Transaction as $transaction) {
                $item = $transaction->Item;
                $lineItems[] = [
                    'item_id' => (string) ($item->ItemID ?? ''),
                    'transaction_id' => (string) ($transaction->TransactionID ?? ''),
                    'order_line_item_id' => (string) ($transaction->OrderLineItemID ?? ''),
                    'sku' => (string) ($item->SKU ?? $transaction->Variation->SKU ?? ''),
                    'title' => (string) ($item->Title ?? ''),
                    'quantity_purchased' => (int) ($transaction->QuantityPurchased ?? 1),
                    'transaction_price' => (float) ($transaction->TransactionPrice ?? 0),
                    'transaction_price_currency' => (string) ($transaction->TransactionPrice['currencyID'] ?? 'USD'),
                    'amount_paid' => (float) ($transaction->AmountPaid ?? 0),
                    'actual_shipping_cost' => (float) ($transaction->ActualShippingCost ?? 0),
                    'actual_handling_cost' => (float) ($transaction->ActualHandlingCost ?? 0),
                    'final_value_fee' => (float) ($transaction->FinalValueFee ?? 0),
                    'listing_type' => (string) ($item->ListingType ?? ''),
                    'condition_id' => (string) ($item->ConditionID ?? ''),
                    'condition_display_name' => (string) ($item->ConditionDisplayName ?? ''),
                    'site' => (string) ($item->Site ?? ''),
                    'variation_attributes' => $this->parseVariationAttributes($transaction),
                    'shipping_service' => (string) ($transaction->ShippingServiceSelected->ShippingService ?? ''),
                    'shipping_cost' => (float) ($transaction->ShippingServiceSelected->ShippingServiceCost ?? 0),
                    'created_date' => (string) ($transaction->CreatedDate ?? ''),
                    'paid_time' => (string) ($transaction->PaidTime ?? ''),
                    'buyer_checkout_message' => (string) ($transaction->BuyerCheckoutMessage ?? ''),
                ];
            }
        }

        // Parse taxes (eBay collected and remit taxes)
        $taxes = [];
        if (isset($firstTransaction->eBayCollectAndRemitTaxes)) {
            $taxNode = $firstTransaction->eBayCollectAndRemitTaxes;
            $taxes = [
                'total_tax_amount' => (float) ($taxNode->TotalTaxAmount ?? 0),
                'tax_amount_currency' => (string) ($taxNode->TotalTaxAmount['currencyID'] ?? 'USD'),
            ];

            if (isset($taxNode->TaxDetails)) {
                $taxes['tax_details'] = [
                    'imposition' => (string) ($taxNode->TaxDetails->Imposition ?? ''),
                    'tax_description' => (string) ($taxNode->TaxDetails->TaxDescription ?? ''),
                    'tax_amount' => (float) ($taxNode->TaxDetails->TaxAmount ?? 0),
                    'tax_on_subtotal' => (float) ($taxNode->TaxDetails->TaxOnSubtotalAmount ?? 0),
                    'tax_on_shipping' => (float) ($taxNode->TaxDetails->TaxOnShippingAmount ?? 0),
                    'tax_on_handling' => (float) ($taxNode->TaxDetails->TaxOnHandlingAmount ?? 0),
                    'collection_method' => (string) ($taxNode->TaxDetails->CollectionMethod ?? ''),
                ];
            }
        }

        // Parse totals
        $subtotal = (float) ($order->Subtotal ?? 0);
        $shippingCost = (float) ($order->ShippingServiceSelected->ShippingServiceCost ?? 0);
        $total = (float) ($order->Total ?? 0);
        $currency = (string) ($order->Total['currencyID'] ?? 'USD');

        return [
            'order_id' => (string) ($order->OrderID ?? ''),
            'extended_order_id' => (string) ($order->ExtendedOrderID ?? $order->OrderID ?? ''),
            'order_status' => (string) ($order->OrderStatus ?? ''),
            'payment_status' => (string) ($order->CheckoutStatus->eBayPaymentStatus ?? ''),
            'checkout_status' => (string) ($order->CheckoutStatus->Status ?? ''),
            'cancel_status' => (string) ($order->CancelStatus ?? ''),

            // Buyer information
            'buyer_email' => (string) ($buyerNode->Email ?? ''),
            'buyer_user_id' => (string) ($buyerNode->UserID ?? $order->BuyerUserID ?? ''),
            'buyer_first_name' => (string) ($buyerNode->UserFirstName ?? ''),
            'buyer_last_name' => (string) ($buyerNode->UserLastName ?? ''),
            'buyer_shipping_address' => $shippingAddress,

            // Order amounts
            'currency' => $currency,
            'order_subtotal' => $subtotal,
            'order_shipping_cost' => $shippingCost,
            'order_total' => $total,
            'order_taxes' => $taxes,

            // Line items
            'line_items' => $lineItems,
            'line_item_count' => count($lineItems),

            // Timestamps
            'created_time' => (string) ($order->CreatedTime ?? ''),
            'paid_time' => (string) ($order->PaidTime ?? ''),
            'shipped_time' => (string) ($order->ShippedTime ?? ''),

            // Shipping details
            'shipping_service' => (string) ($order->ShippingServiceSelected->ShippingService ?? ''),
            'is_multi_leg_shipping' => (string) ($order->IsMultiLegShipping ?? 'false') === 'true',
        ];
    }

    /**
     * Parse variation attributes from transaction
     */
    private function parseVariationAttributes($transaction): ?array
    {
        if (!isset($transaction->Variation->VariationSpecifics->NameValueList)) {
            return null;
        }

        $attributes = [];
        foreach ($transaction->Variation->VariationSpecifics->NameValueList as $spec) {
            $attributes[(string) $spec->Name] = (string) ($spec->Value ?? '');
        }

        return $attributes;
    }

    /**
     * Process and save an eBay order
     */
    private function processEbayOrder(array $ebayOrder, string $salesChannelId): string
    {
        // Check if order already exists
        $existingOrder = Order::where('ebay_order_id', $ebayOrder['order_id'])->first();

        if ($existingOrder) {
            // Update existing order status
            $existingOrder->update([
                'ebay_order_status' => $ebayOrder['order_status'],
                'ebay_payment_status' => $ebayOrder['payment_status'],
                'order_status' => $this->mapEbayOrderStatus($ebayOrder['order_status']),
                'payment_status' => $this->mapEbayPaymentStatus($ebayOrder['payment_status']),
            ]);

            return 'updated';
        }

        // Create new order
        DB::beginTransaction();
        try {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'sales_channel_id' => $salesChannelId,
                'ebay_order_id' => $ebayOrder['order_id'],
                'buyer_username' => $ebayOrder['buyer']['username'],
                'buyer_email' => $ebayOrder['buyer']['email'],
                'buyer_name' => $ebayOrder['shipping_address']['name'] ?? null,
                'buyer_phone' => $ebayOrder['shipping_address']['phone'] ?? null,
                'shipping_name' => $ebayOrder['shipping_address']['name'] ?? null,
                'shipping_address_line1' => $ebayOrder['shipping_address']['street1'] ?? null,
                'shipping_address_line2' => $ebayOrder['shipping_address']['street2'] ?? null,
                'shipping_city' => $ebayOrder['shipping_address']['city'] ?? null,
                'shipping_state' => $ebayOrder['shipping_address']['state'] ?? null,
                'shipping_postal_code' => $ebayOrder['shipping_address']['postal_code'] ?? null,
                'shipping_country' => $ebayOrder['shipping_address']['country'] ?? null,
                'subtotal' => $ebayOrder['subtotal'],
                'shipping_cost' => $ebayOrder['shipping_cost'],
                'total' => $ebayOrder['total'],
                'currency' => $ebayOrder['currency'],
                'order_status' => $this->mapEbayOrderStatus($ebayOrder['order_status']),
                'payment_status' => $this->mapEbayPaymentStatus($ebayOrder['payment_status']),
                'ebay_order_status' => $ebayOrder['order_status'],
                'ebay_payment_status' => $ebayOrder['payment_status'],
                'ebay_raw_data' => $ebayOrder['raw_data'],
                'order_date' => !empty($ebayOrder['created_time']) ? new \DateTime($ebayOrder['created_time']) : now(),
                'paid_at' => !empty($ebayOrder['paid_time']) ? new \DateTime($ebayOrder['paid_time']) : null,
                'shipped_at' => !empty($ebayOrder['shipped_time']) ? new \DateTime($ebayOrder['shipped_time']) : null,
            ]);

            // Create order items
            foreach ($ebayOrder['line_items'] as $lineItem) {
                // Find matching product by SKU (which is the eBay item_id in our system)
                $product = Product::where('sku', $lineItem['item_id'])->first();

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product?->id,
                    'ebay_item_id' => $lineItem['item_id'],
                    'ebay_transaction_id' => $lineItem['transaction_id'],
                    'ebay_line_item_id' => $lineItem['line_item_id'],
                    'sku' => $lineItem['sku'] ?: $lineItem['item_id'],
                    'title' => $lineItem['title'],
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'total_price' => $lineItem['unit_price'] * $lineItem['quantity'],
                    'currency' => $ebayOrder['currency'],
                    'variation_attributes' => $lineItem['variation_attributes'],
                ]);

                // Update inventory if payment is complete
                if ($this->mapEbayPaymentStatus($ebayOrder['payment_status']) === 'paid') {
                    $orderItem->updateInventory();
                }
            }

            DB::commit();

            Log::info('Created eBay order', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrder['order_id'],
                'items_count' => count($ebayOrder['line_items']),
            ]);

            return 'created';

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Map eBay order status to local status
     */
    private function mapEbayOrderStatus(string $ebayStatus): string
    {
        return match (strtolower($ebayStatus)) {
            'active' => 'processing',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
            'inactive' => 'cancelled',
            'shipped' => 'shipped',
            default => 'pending',
        };
    }

    /**
     * Map eBay payment status to local status
     */
    private function mapEbayPaymentStatus(string $ebayStatus): string
    {
        return match (strtolower($ebayStatus)) {
            'nopaymentfailure', 'paymentcomplete' => 'paid',
            'paymentpending' => 'pending',
            'refunded' => 'refunded',
            default => 'pending',
        };
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

    public function getEbayOrders(Request $request, string $id)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            $daysBack = $request->input('days', 30);
            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime("-{$daysBack} days"));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 100);

            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                    <GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <ErrorLanguage>en_US</ErrorLanguage>
                        <WarningLevel>High</WarningLevel>
                        <DetailLevel>ReturnAll</DetailLevel>
                        <CreateTimeFrom>' . $createTimeFrom . '</CreateTimeFrom>
                        <CreateTimeTo>' . $createTimeTo . '</CreateTimeTo>
                        <OrderRole>Seller</OrderRole>
                        <OrderStatus>All</OrderStatus>
                        <Pagination>
                            <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                            <PageNumber>' . $page . '</PageNumber>
                        </Pagination>
                    </GetOrdersRequest>';

            $response = $this->callTradingApi($salesChannel, 'GetOrders', $xmlRequest);
            $result = $this->parseOrdersResponse($response);

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

    /**
     * Handle eBay webhook notifications
     * Supports both Platform Notifications (XML) and Commerce Notification API (JSON)
     */
    public function handleEbayOrderWebhook(Request $request, string $id)
    {
        // Log incoming webhook request
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

        // Hash: SHA-256(challengeCode + verificationToken + endpoint)
        $hashInput = $challengeCode . $verificationToken . $endpoint;
        $challengeResponse = hash('sha256', $hashInput);

        Log::info('eBay Challenge Request handled', [
            'sales_channel_id' => $salesChannel->id,
        ]);

        return response()->json(['challengeResponse' => $challengeResponse]);
    }

    /**
     * Handle Commerce Notification API notifications (JSON)
     * Logs ALL notifications to individual JSON files with timestamps
     */
    protected function handleCommerceApiNotification(Request $request, SalesChannel $salesChannel)
    {
        $payload = $request->all();
        $timestamp = now();
        $topic = $payload['metadata']['topic'] ?? 'unknown';

        // Prepare the notification data
        $notificationData = [
            'timestamp' => $timestamp->toIso8601String(),
            'notification_type' => $topic,
            'sales_channel_id' => $salesChannel->id,
            'sales_channel_name' => $salesChannel->name,
            'data' => $payload,
        ];

        // Save to individual JSON file with timestamp
        $this->saveNotificationToFile($topic, $notificationData, $timestamp);

        // Also log to the main ebay log channel for quick reference
        Log::channel('ebay')->info("Commerce API Notification received: {$topic}", [
            'timestamp' => $timestamp->toIso8601String(),
            'sales_channel_id' => $salesChannel->id,
            'file' => $this->getNotificationFileName($topic, $timestamp),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Handle Platform Notifications (XML from Trading API)
     * Logs ALL notification types to individual JSON files with timestamps
     */
    protected function handlePlatformNotification(Request $request, SalesChannel $salesChannel)
    {
        $rawContent = $request->getContent();
        $timestamp = now();

        try {
            // Clean and parse the XML (handle SOAP namespaces)
            $cleanedXml = $this->cleanSoapXml($rawContent);

            // Suppress XML parsing errors and handle them manually
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($cleanedXml);
            $xmlErrors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors(false);

            if ($xml === false || !empty($xmlErrors)) {
                // XML parsing failed - save raw content as fallback
                Log::channel('ebay')->warning('eBay Platform Notification: XML parsing issues, saving raw content', [
                    'timestamp' => $timestamp->toIso8601String(),
                    'sales_channel_id' => $salesChannel->id,
                    'errors' => array_map(fn($e) => $e->message, $xmlErrors),
                ]);

                // Save raw notification with error info
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

                return response('OK', 200); // Still return OK to eBay
            }

            // Get the notification type from the root element name or body
            $notificationType = $xml->getName();

            // For SOAP notifications, extract the actual notification from Body
            $notificationXml = $xml;
            if ($notificationType === 'Envelope') {
                $body = $xml->Body ?? null;
                if ($body && $body->children()->count() > 0) {
                    $notificationXml = $body->children()[0];
                    $notificationType = $notificationXml->getName();
                }
            }

            // Convert XML to JSON/Array for logging
            $jsonData = $this->xmlToJson($notificationXml);

            // Prepare the notification data
            $notificationData = [
                'timestamp' => $timestamp->toIso8601String(),
                'notification_type' => $notificationType,
                'sales_channel_id' => $salesChannel->id,
                'sales_channel_name' => $salesChannel->name,
                'data' => $jsonData,
            ];

            // Save to individual JSON file with timestamp
            $this->saveNotificationToFile($notificationType, $notificationData, $timestamp);

            // Also log to the main ebay log channel for quick reference
            Log::channel('ebay')->info("Notification received: {$notificationType}", [
                'timestamp' => $timestamp->toIso8601String(),
                'sales_channel_id' => $salesChannel->id,
                'file' => $this->getNotificationFileName($notificationType, $timestamp),
            ]);

            // Process order-related notifications
            if (OrderController::isOrderNotification($notificationType)) {
                try {
                    $orderController = new OrderController();
                    $order = $orderController->processEbayNotification($notificationXml, $salesChannel, $notificationType, $timestamp);

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
     * Clean SOAP XML by removing namespace prefixes and declarations
     * This makes the XML parseable by simplexml_load_string
     */
    protected function cleanSoapXml(string $xmlContent): string
    {
        // Remove XML declaration if present (will be re-added if needed)
        $cleanedXml = preg_replace('/<\?xml[^>]*\?>/', '', $xmlContent);

        // Remove all namespace declarations (xmlns:prefix="..." and xmlns="...")
        $cleanedXml = preg_replace('/\s+xmlns(:[a-zA-Z0-9_-]+)?="[^"]*"/', '', $cleanedXml);

        // Remove namespace prefixes from element names (e.g., <soapenv:Envelope> -> <Envelope>)
        $cleanedXml = preg_replace('/<(\/?)([a-zA-Z0-9_-]+):([a-zA-Z0-9_-]+)/', '<$1$3', $cleanedXml);

        // Remove namespace prefixes from attribute names (e.g., soapenv:mustUnderstand -> mustUnderstand)
        $cleanedXml = preg_replace('/\s+[a-zA-Z0-9_-]+:([a-zA-Z0-9_-]+)=/', ' $1=', $cleanedXml);

        // Remove xsi:type and similar namespaced attributes entirely
        $cleanedXml = preg_replace('/\s+[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+="[^"]*"/', '', $cleanedXml);

        // Clean up any double spaces
        $cleanedXml = preg_replace('/\s+/', ' ', $cleanedXml);

        // Clean up spaces before >
        $cleanedXml = preg_replace('/\s+>/', '>', $cleanedXml);

        // Add XML declaration back
        $cleanedXml = '<?xml version="1.0" encoding="UTF-8"?>' . trim($cleanedXml);

        return $cleanedXml;
    }

    /**
     * Save notification to individual JSON file
     */
    protected function saveNotificationToFile(string $notificationType, array $data, $timestamp): void
    {
        $directory = storage_path('logs/ebay/notifications/' . $timestamp->format('Y-m-d'));

        // Create directory if it doesn't exist
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $this->getNotificationFileName($notificationType, $timestamp);
        $filepath = $directory . '/' . $filename;

        // Save as formatted JSON
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Generate notification filename with timestamp
     */
    protected function getNotificationFileName(string $notificationType, $timestamp): string
    {
        return $timestamp->format('H-i-s') . '_' . $notificationType . '.json';
    }

    /**
     * Convert XML to a clean JSON array
     * Handles attributes, namespaces, and nested elements properly
     */
    protected function xmlToJson($xml): array
    {
        // Get XML string and clean it
        $xmlString = $xml->asXML();
        $cleanedXml = $this->cleanSoapXml($xmlString);

        $cleanXml = simplexml_load_string($cleanedXml);

        if ($cleanXml === false) {
            // If cleaning failed, try to convert the original
            return $this->xmlNodeToArray($xml);
        }

        return $this->xmlNodeToArray($cleanXml);
    }

    /**
     * Recursively convert XML node to array
     */
    protected function xmlNodeToArray($node): array|string
    {
        $result = [];

        // Get attributes
        foreach ($node->attributes() as $attrName => $attrValue) {
            $result['@' . $attrName] = (string) $attrValue;
        }

        // Get child elements
        $children = $node->children();

        if ($children->count() === 0) {
            // No children - return the text content
            $text = trim((string) $node);
            if (!empty($result)) {
                // Has attributes, add text as @value
                if (!empty($text)) {
                    $result['@value'] = $text;
                }
                return $result;
            }
            return $text;
        }

        // Process children
        $childArray = [];
        foreach ($children as $childName => $childNode) {
            $childValue = $this->xmlNodeToArray($childNode);

            // Handle multiple elements with same name (convert to array)
            if (isset($childArray[$childName])) {
                if (!is_array($childArray[$childName]) || !isset($childArray[$childName][0])) {
                    $childArray[$childName] = [$childArray[$childName]];
                }
                $childArray[$childName][] = $childValue;
            } else {
                $childArray[$childName] = $childValue;
            }
        }

        return array_merge($result, $childArray);
    }

    /**
     * Log detailed information based on notification type
     */
    protected function logNotificationDetails(string $notificationType, $xml, SalesChannel $salesChannel, string $timestamp): void
    {
        $details = [];

        // Order & Transaction Events
        if (in_array($notificationType, ['FixedPriceTransaction', 'AuctionCheckoutComplete', 'ItemSold', 'ItemMarkedShipped', 'ItemMarkedPaid', 'ItemReadyForPickup', 'BuyerCancelRequested', 'CheckoutBuyerRequestsTotal', 'PaymentReminder'])) {
            $details = $this->extractTransactionData($xml);
            $details['category'] = 'ORDER_TRANSACTION';
        }
        // Auction Events
        elseif (in_array($notificationType, ['EndOfAuction', 'BidPlaced', 'BidReceived', 'OutBid', 'ItemWon', 'ItemLost', 'BidItemEndingSoon', 'SecondChanceOffer'])) {
            $details = $this->extractAuctionData($xml);
            $details['category'] = 'AUCTION';
        }
        // Best Offer Events
        elseif (in_array($notificationType, ['BestOffer', 'BestOfferPlaced', 'BestOfferDeclined', 'CounterOfferReceived'])) {
            $details = $this->extractBestOfferData($xml);
            $details['category'] = 'BEST_OFFER';
        }
        // Listing Events
        elseif (in_array($notificationType, ['ItemListed', 'ItemRevised', 'ItemRevisedAddCharity', 'ItemExtended', 'ItemClosed', 'ItemUnsold', 'ItemSuspended', 'ItemOutOfStock'])) {
            $details = $this->extractListingData($xml);
            $details['category'] = 'LISTING';
        }
        // Feedback Events
        elseif (in_array($notificationType, ['Feedback', 'FeedbackLeft', 'FeedbackReceived', 'FeedbackStarChanged'])) {
            $details = $this->extractFeedbackData($xml);
            $details['category'] = 'FEEDBACK';
        }
        // Message Events
        elseif (in_array($notificationType, ['AskSellerQuestion', 'MyMessageseBayMessage', 'MyMessagesM2MMessage', 'MyMessagesHighPriorityMessage', 'MyMessageseBayMessageHeader', 'MyMessagesM2MMessageHeader', 'MyMessagesHighPriorityMessageHeader', 'M2MMessageStatusChange'])) {
            $details = $this->extractMessageData($xml);
            $details['category'] = 'MESSAGE';
        }
        // Return Events
        elseif (in_array($notificationType, ['ReturnCreated', 'ReturnShipped', 'ReturnDelivered', 'ReturnClosed', 'ReturnEscalated', 'ReturnRefundOverdue', 'ReturnSellerInfoOverdue', 'ReturnWaitingForSellerInfo'])) {
            $details = $this->extractReturnData($xml);
            $details['category'] = 'RETURN';
        }
        // Case/Dispute Events
        elseif (in_array($notificationType, ['EBPClosedCase', 'EBPEscalatedCase', 'EBPMyResponseDue', 'EBPOtherPartyResponseDue', 'EBPAppealedCase', 'EBPClosedAppeal', 'EBPOnHoldCase', 'EBPMyPaymentDue', 'EBPPaymentDone', 'INRBuyerRespondedToDispute', 'OrderInquiryReminderForEscalation'])) {
            $details = $this->extractCaseData($xml);
            $details['category'] = 'CASE_DISPUTE';
        }
        // Watch List Events
        elseif (in_array($notificationType, ['ItemAddedToWatchList', 'ItemRemovedFromWatchList', 'WatchedItemEndingSoon', 'ShoppingCartItemEndingSoon'])) {
            $details = $this->extractWatchListData($xml);
            $details['category'] = 'WATCH_LIST';
        }
        // Account Events
        elseif ($notificationType === 'TokenRevocation') {
            $details = ['category' => 'ACCOUNT', 'event' => 'Token revocation requested'];
        }
        else {
            $details = ['category' => 'UNKNOWN', 'raw_root_element' => $xml->getName()];
        }

        Log::channel('ebay')->info("Notification Details: {$notificationType}", [
            'timestamp' => $timestamp,
            'notification_type' => $notificationType,
            'sales_channel_id' => $salesChannel->id,
            'details' => $details,
        ]);
    }

    /**
     * Extract auction-related data from XML
     */
    protected function extractAuctionData($xml): array
    {
        return [
            'item_id' => (string) ($xml->Item->ItemID ?? $xml->ItemID ?? ''),
            'title' => (string) ($xml->Item->Title ?? ''),
            'current_price' => (float) ($xml->Item->SellingStatus->CurrentPrice ?? 0),
            'bid_count' => (int) ($xml->Item->SellingStatus->BidCount ?? 0),
            'high_bidder' => (string) ($xml->Item->SellingStatus->HighBidder->UserID ?? ''),
            'end_time' => (string) ($xml->Item->ListingDetails->EndTime ?? ''),
        ];
    }

    /**
     * Extract best offer data from XML
     */
    protected function extractBestOfferData($xml): array
    {
        return [
            'item_id' => (string) ($xml->Item->ItemID ?? $xml->ItemID ?? ''),
            'best_offer_id' => (string) ($xml->BestOffer->BestOfferID ?? ''),
            'offer_price' => (float) ($xml->BestOffer->Price ?? 0),
            'buyer_user_id' => (string) ($xml->BestOffer->Buyer->UserID ?? ''),
            'status' => (string) ($xml->BestOffer->Status ?? ''),
            'quantity' => (int) ($xml->BestOffer->Quantity ?? 1),
        ];
    }

    /**
     * Extract listing data from XML
     */
    protected function extractListingData($xml): array
    {
        return [
            'item_id' => (string) ($xml->Item->ItemID ?? $xml->ItemID ?? ''),
            'title' => (string) ($xml->Item->Title ?? ''),
            'listing_status' => (string) ($xml->Item->SellingStatus->ListingStatus ?? ''),
            'current_price' => (float) ($xml->Item->SellingStatus->CurrentPrice ?? 0),
            'quantity' => (int) ($xml->Item->Quantity ?? 0),
            'quantity_sold' => (int) ($xml->Item->SellingStatus->QuantitySold ?? 0),
        ];
    }

    /**
     * Extract feedback data from XML
     */
    protected function extractFeedbackData($xml): array
    {
        return [
            'item_id' => (string) ($xml->FeedbackDetail->ItemID ?? ''),
            'feedback_id' => (string) ($xml->FeedbackDetail->FeedbackID ?? ''),
            'comment_type' => (string) ($xml->FeedbackDetail->CommentType ?? ''),
            'comment_text' => (string) ($xml->FeedbackDetail->CommentText ?? ''),
            'user_id' => (string) ($xml->FeedbackDetail->CommentingUser ?? ''),
            'role' => (string) ($xml->FeedbackDetail->Role ?? ''),
        ];
    }

    /**
     * Extract message data from XML
     */
    protected function extractMessageData($xml): array
    {
        return [
            'message_id' => (string) ($xml->Message->MessageID ?? $xml->MessageID ?? ''),
            'item_id' => (string) ($xml->Message->ItemID ?? $xml->ItemID ?? ''),
            'sender' => (string) ($xml->Message->Sender ?? ''),
            'subject' => (string) ($xml->Message->Subject ?? ''),
            'message_type' => (string) ($xml->Message->MessageType ?? ''),
            'question_type' => (string) ($xml->Message->QuestionType ?? ''),
        ];
    }

    /**
     * Extract return data from XML
     */
    protected function extractReturnData($xml): array
    {
        return [
            'return_id' => (string) ($xml->ReturnId ?? $xml->Return->ReturnId ?? ''),
            'item_id' => (string) ($xml->ItemId ?? $xml->Return->ItemId ?? ''),
            'order_id' => (string) ($xml->OrderId ?? $xml->Return->OrderId ?? ''),
            'return_status' => (string) ($xml->ReturnStatus ?? $xml->Return->ReturnStatus ?? ''),
            'return_reason' => (string) ($xml->ReturnReason ?? $xml->Return->ReturnReason ?? ''),
            'buyer_user_id' => (string) ($xml->BuyerUserId ?? $xml->Return->BuyerUserId ?? ''),
        ];
    }

    /**
     * Extract case/dispute data from XML
     */
    protected function extractCaseData($xml): array
    {
        return [
            'case_id' => (string) ($xml->CaseId ?? $xml->DisputeID ?? ''),
            'item_id' => (string) ($xml->ItemID ?? ''),
            'case_type' => (string) ($xml->CaseType ?? $xml->DisputeReason ?? ''),
            'case_status' => (string) ($xml->CaseStatus ?? $xml->DisputeState ?? ''),
            'buyer_user_id' => (string) ($xml->BuyerUserID ?? ''),
            'response_due_date' => (string) ($xml->ResponseDueDate ?? ''),
        ];
    }

    /**
     * Extract watch list data from XML
     */
    protected function extractWatchListData($xml): array
    {
        return [
            'item_id' => (string) ($xml->Item->ItemID ?? $xml->ItemID ?? ''),
            'title' => (string) ($xml->Item->Title ?? ''),
            'watch_count' => (int) ($xml->Item->WatchCount ?? 0),
            'end_time' => (string) ($xml->Item->ListingDetails->EndTime ?? ''),
        ];
    }

    /**
     * Extract transaction data from XML notification
     */
    protected function extractTransactionData($xml): array
    {
        $data = [
            'item_id' => null,
            'transaction_id' => null,
            'order_id' => null,
            'buyer_user_id' => null,
            'buyer_email' => null,
            'quantity_purchased' => null,
            'transaction_price' => null,
            'created_date' => null,
        ];

        // Try different XML structures (SOAP vs direct)
        $transaction = $xml->Transaction ?? $xml->Body->GetItemTransactionsResponse->TransactionArray->Transaction ?? null;

        if ($transaction) {
            $data['item_id'] = (string) ($transaction->Item->ItemID ?? $xml->Item->ItemID ?? '');
            $data['transaction_id'] = (string) ($transaction->TransactionID ?? '');
            $data['order_id'] = (string) ($transaction->ContainingOrder->OrderID ?? '');
            $data['buyer_user_id'] = (string) ($transaction->Buyer->UserID ?? '');
            $data['buyer_email'] = (string) ($transaction->Buyer->Email ?? '');
            $data['quantity_purchased'] = (int) ($transaction->QuantityPurchased ?? 1);
            $data['transaction_price'] = (float) ($transaction->TransactionPrice ?? 0);
            $data['created_date'] = (string) ($transaction->CreatedDate ?? '');
        }

        return $data;
    }

    /**
     * Extract order data from XML notification
     */
    protected function extractOrderData($xml): array
    {
        $data = [
            'order_id' => null,
            'order_status' => null,
            'buyer_user_id' => null,
            'total' => null,
            'subtotal' => null,
            'shipping_cost' => null,
            'created_time' => null,
            'paid_time' => null,
            'items' => [],
        ];

        $order = $xml->Order ?? $xml->Body->Order ?? null;

        if ($order) {
            $data['order_id'] = (string) ($order->OrderID ?? '');
            $data['order_status'] = (string) ($order->OrderStatus ?? '');
            $data['buyer_user_id'] = (string) ($order->BuyerUserID ?? '');
            $data['total'] = (float) ($order->Total ?? 0);
            $data['subtotal'] = (float) ($order->Subtotal ?? 0);
            $data['shipping_cost'] = (float) ($order->ShippingServiceSelected->ShippingServiceCost ?? 0);
            $data['created_time'] = (string) ($order->CreatedTime ?? '');
            $data['paid_time'] = (string) ($order->PaidTime ?? '');

            // Extract line items
            if (isset($order->TransactionArray->Transaction)) {
                foreach ($order->TransactionArray->Transaction as $transaction) {
                    $data['items'][] = [
                        'item_id' => (string) ($transaction->Item->ItemID ?? ''),
                        'title' => (string) ($transaction->Item->Title ?? ''),
                        'sku' => (string) ($transaction->Item->SKU ?? ''),
                        'quantity' => (int) ($transaction->QuantityPurchased ?? 1),
                        'price' => (float) ($transaction->TransactionPrice ?? 0),
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Create an eBay listing for a product
     * This is a wrapper method called by ProductController
     */
    public function createEbayListing(SalesChannel $channel, Product $product): array
    {
        try {
            $ebayService = new EbayService();

            // Prepare item data from product
            $itemData = $this->prepareItemDataFromProduct($product, $channel);

            Log::info('Creating eBay listing', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'channel_id' => $channel->id,
            ]);

            $result = $ebayService->addFixedPriceItem($channel, $itemData);

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
     * This is a wrapper method called by ProductController
     */
    public function reviseEbayItem(SalesChannel $channel, Product $product, string $itemId): array
    {
        try {
            $ebayService = new EbayService();

            // Prepare item data from product
            $itemData = $this->prepareItemDataFromProduct($product, $channel);

            Log::info('Revising eBay listing', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'sku' => $product->sku,
                'channel_id' => $channel->id,
            ]);

            $result = $ebayService->reviseFixedPriceItem($channel, $itemId, $itemData);

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
     * This is a wrapper method called by ProductController
     */
    public function endEbayItem(SalesChannel $channel, string $itemId, string $reason = 'NotAvailable'): array
    {
        try {
            $ebayService = new EbayService();

            Log::info('Ending eBay listing', [
                'item_id' => $itemId,
                'reason' => $reason,
                'channel_id' => $channel->id,
            ]);

            $result = $ebayService->endFixedPriceItem($channel, $itemId, $reason);

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
     * This is a wrapper method called by ProductController
     */
    public function findEbayListingBySku(SalesChannel $channel, string $sku): ?array
    {
        try {
            $ebayService = new EbayService();

            Log::info('Finding eBay listing by SKU', [
                'sku' => $sku,
                'channel_id' => $channel->id,
            ]);

            $result = $ebayService->findListingBySku($channel, $sku);

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
     * Relist an ended eBay item
     * This is a wrapper method called by ProductController
     */
    public function relistEbayItem(SalesChannel $channel, Product $product, string $itemId): array
    {
        try {
            $ebayService = new EbayService();

            // Prepare item data from product for any updates
            $itemData = $this->prepareItemDataFromProduct($product, $channel);

            Log::info('Relisting eBay item', [
                'product_id' => $product->id,
                'item_id' => $itemId,
                'channel_id' => $channel->id,
            ]);

            $result = $ebayService->relistFixedPriceItem($channel, $itemId, $itemData);

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

    /**
     * Prepare item data array from a Product model for eBay API
     */
    private function prepareItemDataFromProduct(Product $product, SalesChannel $channel): array
    {
        $productMeta = $product->product_meta;

        // Determine price - use sale_price if available, otherwise regular_price
        $price = $productMeta['sale_price'] ?? $productMeta['regular_price'] ?? $product->price ?? 0;

        // Determine description - check for empty strings, not just null
        $description = $product->description;
        if (empty($description)) {
            $description = $product->short_description;
        }
        if (empty($description)) {
            $description = $product->name;
        }

        // Build item data array
        $itemData = [
            // 'title' => $product->name,
            'description' => $description,
            'sku' => $product->sku,
            // 'price' => $price,
            'quantity' => $product->stock_quantity ?? 1,
            'condition_id' => 1000, // New
            'listing_duration' => 'GTC', // Good Till Cancelled
            'currency' => 'USD',
            'country' => 'US',
            'location' => 'United States',
        ];

        // Add weight and dimensions if available
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

        // Add category ID if we have a mapping
        // For now, use a default category - this should be mapped from the product category
        if ($product->category_id) {
            // You may want to create a category mapping table for eBay categories
            // For now, we'll use a default eBay category if not mapped
            $itemData['category_id'] = $this->getEbayCategoryId($product->category_id);
        }

        // Add product image if available
        if (!empty($product->product_image)) {
            $itemData['image_url'] = asset('storage/' . $product->product_image);
        }

        return $itemData;
    }

    /**
     * Get eBay category ID from local category ID
     * This can be expanded to use a category mapping table
     */
    private function getEbayCategoryId(int $localCategoryId): string
    {
        // TODO: Implement proper category mapping
        // For now, return a default eBay category (e.g., "Other" category)
        // Category 99 is the general "Everything Else" category
        // You should create a mapping table to properly map local categories to eBay categories

        // Example mapping - extend this as needed
        $categoryMapping = [
            // localCategoryId => ebayCategoryId
        ];

        return $categoryMapping[$localCategoryId] ?? '99'; // Default to "Other"
    }
}
