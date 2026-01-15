<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Http\Request;
use App\Services\EbayService;
use Illuminate\Support\Facades\Http;

class EbayController extends Controller
{
    private const EBAY_API_URL = 'https://api.ebay.com/ws/api.dll';
    private const EBAY_TOKEN_URL = 'https://api.ebay.com/identity/v1/oauth2/token';
    private const API_COMPATIBILITY_LEVEL = '967';
    private const API_SITE_ID = '0'; // US

    public function __construct(protected EbayService $ebayService) {}

    /**
     * Get active listings (paginated)
     */
    // public function getActiveListings(Request $request, string $id)
    // {
    //     try {
    //         $salesChannel = $this->getSalesChannelWithValidToken($id);

    //         $listings = $this->ebayService->getActiveListings(
    //             $salesChannel,
    //             $request->input('page', 1),
    //             $request->input('per_page', 100)
    //         );

    //         return response()->json($listings);
    //     } catch (\Exception $e) {
    //         return $this->errorResponse($e->getMessage());
    //     }
    // }

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
            // $listings = $this->ebayService->getAllActiveListings($salesChannel);

            return response()->json($listings);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get ALL active listings (auto-pagination)
     */
    // public function getAllActiveListings(SalesChannel $salesChannel): array
    // {
    //     $allItems = [];
    //     $page = 1;

    //     do {
    //         $response = $this->getActiveListings($salesChannel, $page, 200);
    //         $allItems = array_merge($allItems, $response['items']);
    //         $totalPages = $response['pagination']['totalPages'];
    //         $page++;
    //     } while ($page <= $totalPages);

    //     return [
    //         'success' => true,
    //         'total_items' => count($allItems),
    //         'items' => $allItems,
    //     ];
    // }

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
     * Get unsold listings (paginated)
     */
    // public function getUnsoldListings(Request $request, string $id)
    // {
    //     try {
    //         $salesChannel = $this->getSalesChannelWithValidToken($id);

    //         $listings = $this->ebayService->getUnsoldListings(
    //             $salesChannel,
    //             $request->input('page', 1),
    //             $request->input('per_page', 100),
    //             $request->input('days', 60)
    //         );

    //         return response()->json($listings);
    //     } catch (\Exception $e) {
    //         return $this->errorResponse($e->getMessage());
    //     }
    // }

    /**
     * Get ALL unsold listings
     */
    // public function getAllUnsoldListings(Request $request, string $id)
    // {
    //     try {
    //         $salesChannel = $this->getSalesChannelWithValidToken($id);
    //         $listings = $this->ebayService->getAllUnsoldListings(
    //             $salesChannel,
    //             $request->input('days', 60)
    //         );

    //         return response()->json($listings);
    //     } catch (\Exception $e) {
    //         return $this->errorResponse($e->getMessage());
    //     }
    // }

    /**
     * Get single item details
     */
    // public function getItemDetails(string $id, string $itemId)
    // {
    //     try {
    //         $salesChannel = $this->getSalesChannelWithValidToken($id);
    //         $itemDetails = $this->ebayService->getItemDetails($salesChannel, $itemId);

    //         return response()->json($itemDetails);
    //     } catch (\Exception $e) {
    //         return $this->errorResponse($e->getMessage());
    //     }
    // }

    /**
     * Redirect user to eBay for authorization
     */
    // public function redirectToEbay(string $id)
    // {
    //     $salesChannel = SalesChannel::findOrFail($id);
    //     $state = $id . '|' . bin2hex(random_bytes(16));
    //     session(['ebay_oauth_state' => $state]);

    //     return redirect()->away($this->ebayService->getAuthorizationUrl($salesChannel, $state));
    // }

    /**
     * Handle OAuth callback from eBay
     */
    // public function callback(Request $request)
    // {
    //     \Log::info('eBay OAuth Callback', [
    //         'all_params' => $request->all(),
    //         'session_state' => session('ebay_oauth_state'),
    //     ]);

    //     try {
    //         $stateParam = $request->input('state');
    //         $sessionState = session('ebay_oauth_state');

    //         if ($stateParam !== $sessionState) {
    //             throw new \Exception('Invalid state parameter');
    //         }

    //         $stateParts = explode('|', $stateParam);
    //         $salesChannelId = $stateParts[0] ?? null;

    //         if (!$salesChannelId) {
    //             throw new \Exception('Sales channel ID not found in state');
    //         }

    //         $salesChannel = SalesChannel::findOrFail($salesChannelId);

    //         if ($request->has('error')) {
    //             throw new \Exception('eBay authorization failed: ' . $request->input('error_description', $request->input('error')));
    //         }

    //         $code = $request->input('code');
    //         $tokenData = $this->ebayService->getUserAccessToken($salesChannel, $code);

    //         $salesChannel->authorization_code = $code;
    //         $salesChannel->access_token = $tokenData['access_token'];
    //         $salesChannel->access_token_expires_at = now()->addSeconds($tokenData['expires_in']);

    //         if (isset($tokenData['refresh_token'])) {
    //             $salesChannel->refresh_token = $tokenData['refresh_token'];
    //             if (isset($tokenData['refresh_token_expires_in'])) {
    //                 $salesChannel->refresh_token_expires_at = now()->addSeconds($tokenData['refresh_token_expires_in']);
    //             }
    //         }

    //         $salesChannel->save();

    //         return redirect()->route('sales-channels.index')
    //             ->with('success', 'Successfully connected to eBay!');
    //     } catch (\Exception $e) {
    //         \Log::error('eBay Callback Error', ['message' => $e->getMessage()]);

    //         return redirect()->route('sales-channels.index')
    //             ->with('error', 'eBay authorization failed: ' . $e->getMessage());
    //     }
    // }

    /**
     * Return error response
     */
    // private function errorResponse(string $message, int $status = 500)
    // {
    //     return response()->json([
    //         'success' => false,
    //         'message' => $message,
    //     ], $status);
    // }
}
