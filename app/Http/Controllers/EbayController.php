<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Http\Request;
use App\Services\EbayService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EbayController extends Controller
{
    private const EBAY_API_URL = 'https://api.ebay.com/ws/api.dll';
    private const EBAY_TOKEN_URL = 'https://api.ebay.com/identity/v1/oauth2/token';
    private const API_COMPATIBILITY_LEVEL = '967';
    private const API_SITE_ID = '0'; // US

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
                dd($response);
                $response =  $this->parseActiveListingsResponse($response);


                $allItems = array_merge($allItems, $response['items']);
                $totalPages = $response['pagination']['totalPages'];
                $page++;
            } while ($page <= $totalPages);

            $listings =  [
                'success' => true,
                'total_items' => count($allItems),
                'items' => $allItems,
            ];

            // dd($allItems);

            foreach ($allItems as $item) {
                if (empty($item['sku'])) {
                    updateListing(
                        array( 'sku' => $item['item_id'] ),
                        $id,
                        $item['item_id']
                    );
                }

                $product = new Product();
                $product->name = $item['title'];
                $product->sku = empty($item['sku']) ? $item['item_id'] : $item['sku'];
                $product->barcode = empty($item['sku']) ? $item['item_id'] : $item['sku'];
                // $product->category_id = $item['category_id'];
                $product->save();

                $product->product_meta()->createMany([
                    [
                        'meta_key' => 'weight',
                        'meta_value' => $request->weight,
                    ],
                    [
                        'meta_key' => 'length',
                        'meta_value' => $request->length,
                    ],
                    [
                        'meta_key' => 'width',
                        'meta_value' => $request->width,
                    ],
                    [
                        'meta_key' => 'height',
                        'meta_value' => $request->height,
                    ],
                    [
                        'meta_key' => 'regular_price',
                        'meta_value' => $request->regular_price,
                    ],
                    [
                        'meta_key' => 'sale_price',
                        'meta_value' => $request->sale_price,
                    ],
                    [
                        'meta_key' => 'alert_quantity',
                        'meta_value' => $request->alert_quantity ?? 0,
                    ]
                ]);
                // $product->stock_quantity = $item['stock_quantity'];
                // Here you can implement logic to save or update the item in your database
                // For example:
                // Product::updateOrCreate(
                //     ['ebay_item_id' => $item['item_id']],
                //     [
                //         'title' => $item['title'],
                //         'sku' => $item['sku'],
                //         'price' => $item['price']['value'],
                //         'currency' => $item['price']['currency'],
                //         'quantity' => $item['quantity'],
                //         'listing_url' => $item['listing_url'],
                //         // Add other fields as necessary
                //     ]
                // );
            }
            // $listings = $this->ebayService->getAllActiveListings($salesChannel);

            return response()->json($listings);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Call eBay Trading API
     */
    private function callTradingApi(SalesChannel $salesChannel, string $callName, string $xmlRequest): string
    {
        $response = Http::timeout(120)
            ->connectTimeout(30)
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
        $parsed = [
            'item_id' => (string) $item->ItemID,
            'title' => (string) $item->Title,
            'sku' => (string) ($item->SKU ?? ''),
            'price' => [
                'value' => (float) ($item->SellingStatus->CurrentPrice ?? $item->BuyItNowPrice ?? $item->StartPrice ?? 0),
                'currency' => (string) ($item->SellingStatus->CurrentPrice['currencyID'] ?? 'USD'),
            ],
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
    public function updateListing(array $data, string $id, string $itemId)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            // Build the ReviseItem XML request
            $xmlParts = [];

            // Title
            if ($data['title']) {
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
                return $this->errorResponse('No fields provided to update', 400);
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

            $response = $this->callTradingApi($salesChannel, 'ReviseItem', $xmlRequest);
            $result = $this->parseReviseItemResponse($response);

            return response()->json($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
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
