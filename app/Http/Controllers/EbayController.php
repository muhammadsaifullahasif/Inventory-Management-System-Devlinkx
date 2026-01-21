<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Rack;
use App\Models\Product;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use App\Models\SalesChannel;
use Illuminate\Http\Request;
use App\Services\EbayService;
use App\Jobs\ImportEbayListings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class EbayController extends Controller
{
    private const EBAY_API_URL = 'https://api.ebay.com/ws/api.dll';
    private const EBAY_TOKEN_URL = 'https://api.ebay.com/identity/v1/oauth2/token';
    private const API_COMPATIBILITY_LEVEL = '967';
    private const API_SITE_ID = '0'; // US

    public function __construct(protected EbayService $ebayService) {}

    /**
     * Get ALL active listings - dispatches job queue for async processing
     */
    public function getAllActiveListings(string $id)
    {
        try {
            // Verify sales channel exists and has valid token
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            // Dispatch the import job to queue
            ImportEbayListings::dispatch($id, 'active')
                ->onQueue('default');

            Log::info('eBay active listings import job dispatched', [
                'sales_channel_id' => $id,
                'sales_channel_name' => $salesChannel->name,
            ]);

            return redirect()->back()->with('success', 'eBay listings import has been queued. The import will start processing shortly. Check the queue status or logs for updates.');
        } catch (\Exception $e) {
            Log::error('Failed to dispatch eBay import job', [
                'sales_channel_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($e->getMessage());
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
