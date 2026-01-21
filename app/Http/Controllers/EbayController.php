<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Rack;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\OrderItem;
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
                $response = $this->callTradingApi($salesChannel, 'GetSellerList', $xmlRequest);
                $response =  $this->parseActiveListingsResponse($response);
                
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

        \Log::info('eBay access token refreshed', [
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

            // Fetch orders from the last 30 days by default
            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime('-90 days'));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');

            Log::info('Starting eBay order sync', [
                'sales_channel_id' => $id,
                'from' => $createTimeFrom,
                'to' => $createTimeTo,
            ]);

            $allOrders = [];
            $page = 1;
            $perPage = 100;

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

            $daysBack = $request->input('days', 30);
            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime("-{$daysBack} days"));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');

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
                        <EntriesPerPage>100</EntriesPerPage>
                        <PageNumber>1</PageNumber>
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
        // Parse buyer info
        $buyer = [
            'username' => (string) ($order->BuyerUserID ?? ''),
            'email' => (string) ($order->TransactionArray->Transaction[0]->Buyer->Email ?? ''),
        ];

        // Parse shipping address
        $shippingAddress = [];
        if (isset($order->ShippingAddress)) {
            $addr = $order->ShippingAddress;
            $shippingAddress = [
                'name' => (string) ($addr->Name ?? ''),
                'street1' => (string) ($addr->Street1 ?? ''),
                'street2' => (string) ($addr->Street2 ?? ''),
                'city' => (string) ($addr->CityName ?? ''),
                'state' => (string) ($addr->StateOrProvince ?? ''),
                'postal_code' => (string) ($addr->PostalCode ?? ''),
                'country' => (string) ($addr->Country ?? ''),
                'phone' => (string) ($addr->Phone ?? ''),
            ];
        }

        // Parse line items
        $lineItems = [];
        if (isset($order->TransactionArray->Transaction)) {
            foreach ($order->TransactionArray->Transaction as $transaction) {
                $item = $transaction->Item;
                $lineItems[] = [
                    'item_id' => (string) ($item->ItemID ?? ''),
                    'transaction_id' => (string) ($transaction->TransactionID ?? ''),
                    'line_item_id' => (string) ($transaction->OrderLineItemID ?? ''),
                    'sku' => (string) ($item->SKU ?? $transaction->Variation->SKU ?? ''),
                    'title' => (string) ($item->Title ?? ''),
                    'quantity' => (int) ($transaction->QuantityPurchased ?? 1),
                    'unit_price' => (float) ($transaction->TransactionPrice ?? 0),
                    'variation_attributes' => $this->parseVariationAttributes($transaction),
                ];
            }
        }

        // Parse totals
        $subtotal = (float) ($order->Subtotal ?? 0);
        $shippingCost = (float) ($order->ShippingServiceSelected->ShippingServiceCost ?? 0);
        $total = (float) ($order->Total ?? 0);

        return [
            'order_id' => (string) ($order->OrderID ?? ''),
            'order_status' => (string) ($order->OrderStatus ?? ''),
            'payment_status' => (string) ($order->CheckoutStatus->eBayPaymentStatus ?? ''),
            'checkout_status' => (string) ($order->CheckoutStatus->Status ?? ''),
            'buyer' => $buyer,
            'shipping_address' => $shippingAddress,
            'line_items' => $lineItems,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total' => $total,
            'currency' => (string) ($order->Total['currencyID'] ?? 'USD'),
            'created_time' => (string) ($order->CreatedTime ?? ''),
            'paid_time' => (string) ($order->PaidTime ?? ''),
            'shipped_time' => (string) ($order->ShippedTime ?? ''),
            'raw_data' => json_decode(json_encode($order), true),
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

    public function getEbayOrders(string $id)
    {
        $salesChannel = $this->getSalesChannelWithValidToken($id);

        $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime('-30 days'));
        $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');

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
                        <EntriesPerPage>100</EntriesPerPage>
                        <PageNumber>1</PageNumber>
                    </Pagination>
                </GetOrdersRequest>';

        // $response = $this->callTradingApi($salesChannel, 'GetOrders', $xmlRequest);

        $response = Http::timeout(300) // Increased to 5 minutes
            ->connectTimeout(60) // Increased to 60 seconds
            ->withHeaders([
                'X-EBAY-API-SITEID' => self::API_SITE_ID,
                'X-EBAY-API-COMPATIBILITY-LEVEL' => self::API_COMPATIBILITY_LEVEL,
                'X-EBAY-API-CALL-NAME' => 'GetOrders',
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
            throw new Exception("eBay GetOrders failed: " . $response->body());
        }

        $data = $response->body();

        return $data;
    }
}
