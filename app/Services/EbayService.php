<?php

namespace App\Services;

use App\Models\SalesChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EbayService
{
    /**
     * Refresh user access token using credentials from SalesChannel
     */
    public function refreshUserToken(SalesChannel $salesChannel): array
    {
        try {
            $clientId = $salesChannel->client_id;
            $clientSecret = $salesChannel->client_secret;
            $scopes = $salesChannel->user_scopes;

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                ])
                ->asForm()
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $salesChannel->refresh_token,
                    'scope' => $scopes,
                ]);

            Log::info('eBay Refresh Token Response', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay Refresh Token Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay refresh token failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay refreshUserToken Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Get all active listings from eBay using Trading API (GetSellerList)
     * This returns detailed item information including images, descriptions, categories
     */
    public function getActiveSellerListings(SalesChannel $salesChannel, int $page = 1, int $entriesPerPage = 100): array
    {
        try {
            $xmlRequest = $this->buildGetSellerListRequest($page, $entriesPerPage);

            $response = Http::timeout(120)
                ->connectTimeout(30)
                ->withHeaders([
                    'X-EBAY-API-SITEID' => '0', // 0 = US
                    'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                    'X-EBAY-API-CALL-NAME' => 'GetSellerList',
                    'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                    'Content-Type' => 'text/xml',
                ])
                ->withBody($xmlRequest, 'text/xml')
                ->post('https://api.ebay.com/ws/api.dll');

            Log::info('eBay GetSellerList Response', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay GetSellerList Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay GetSellerList failed: ' . $response->body());
            }

            return $this->parseGetSellerListResponse($response->body());
        } catch (Exception $e) {
            Log::error('eBay getActiveSellerListings Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Build XML request for GetSellerList - returns full item details
     */
    private function buildGetSellerListRequest(int $page, int $entriesPerPage): string
    {
        // Use EndTime range to get active listings (items ending in the future)
        $endTimeFrom = gmdate('Y-m-d\TH:i:s\Z');
        $endTimeTo = gmdate('Y-m-d\TH:i:s\Z', strtotime('+120 days'));

        return '<?xml version="1.0" encoding="utf-8"?>
<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ErrorLanguage>en_US</ErrorLanguage>
    <WarningLevel>High</WarningLevel>
    <DetailLevel>ReturnAll</DetailLevel>
    <EndTimeFrom>' . $endTimeFrom . '</EndTimeFrom>
    <EndTimeTo>' . $endTimeTo . '</EndTimeTo>
    <IncludeWatchCount>true</IncludeWatchCount>
    <Pagination>
        <EntriesPerPage>' . $entriesPerPage . '</EntriesPerPage>
        <PageNumber>' . $page . '</PageNumber>
    </Pagination>
    <GranularityLevel>Fine</GranularityLevel>
</GetSellerListRequest>';
    }

    /**
     * Get unsold listings from eBay using GetMyeBaySelling API
     * These are listings that ended without a sale (expired, not sold)
     * Note: eBay "Drafts" in Seller Hub are NOT accessible via API
     */
    public function getUnsoldListings(SalesChannel $salesChannel, int $page = 1, int $entriesPerPage = 100, int $durationInDays = 60): array
    {
        try {
            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ErrorLanguage>en_US</ErrorLanguage>
    <WarningLevel>High</WarningLevel>
    <UnsoldList>
        <Include>true</Include>
        <DurationInDays>' . $durationInDays . '</DurationInDays>
        <Pagination>
            <EntriesPerPage>' . $entriesPerPage . '</EntriesPerPage>
            <PageNumber>' . $page . '</PageNumber>
        </Pagination>
    </UnsoldList>
    <DetailLevel>ReturnAll</DetailLevel>
</GetMyeBaySellingRequest>';

            $response = Http::timeout(120)
                ->connectTimeout(30)
                ->withHeaders([
                    'X-EBAY-API-SITEID' => '0',
                    'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                    'X-EBAY-API-CALL-NAME' => 'GetMyeBaySelling',
                    'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                    'Content-Type' => 'text/xml',
                ])
                ->withBody($xmlRequest, 'text/xml')
                ->post('https://api.ebay.com/ws/api.dll');

            Log::info('eBay GetMyeBaySelling (Unsold) Response', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay GetMyeBaySelling (Unsold) Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay GetMyeBaySelling failed: ' . $response->body());
            }

            return $this->parseGetMyeBaySellingResponse($response->body(), 'UnsoldList');
        } catch (Exception $e) {
            Log::error('eBay getUnsoldListings Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Alias for getUnsoldListings (for backward compatibility)
     */
    public function getDraftListings(SalesChannel $salesChannel, int $page = 1, int $entriesPerPage = 100): array
    {
        return $this->getUnsoldListings($salesChannel, $page, $entriesPerPage);
    }

    /**
     * Get scheduled listings from eBay using GetMyeBaySelling API
     */
    public function getScheduledListings(SalesChannel $salesChannel, int $page = 1, int $entriesPerPage = 100): array
    {
        try {
            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ErrorLanguage>en_US</ErrorLanguage>
    <WarningLevel>High</WarningLevel>
    <ScheduledList>
        <Include>true</Include>
        <Pagination>
            <EntriesPerPage>' . $entriesPerPage . '</EntriesPerPage>
            <PageNumber>' . $page . '</PageNumber>
        </Pagination>
    </ScheduledList>
    <DetailLevel>ReturnAll</DetailLevel>
</GetMyeBaySellingRequest>';

            $response = Http::timeout(120)
                ->connectTimeout(30)
                ->withHeaders([
                    'X-EBAY-API-SITEID' => '0',
                    'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                    'X-EBAY-API-CALL-NAME' => 'GetMyeBaySelling',
                    'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                    'Content-Type' => 'text/xml',
                ])
                ->withBody($xmlRequest, 'text/xml')
                ->post('https://api.ebay.com/ws/api.dll');

            Log::info('eBay GetMyeBaySelling (Scheduled) Response', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                throw new Exception('eBay GetMyeBaySelling failed: ' . $response->body());
            }

            return $this->parseGetMyeBaySellingResponse($response->body(), 'ScheduledList');
        } catch (Exception $e) {
            Log::error('eBay getScheduledListings Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Parse GetMyeBaySelling response for different list types
     */
    private function parseGetMyeBaySellingResponse(string $xmlResponse, string $listType = 'ActiveList'): array
    {
        $xml = simplexml_load_string($xmlResponse);

        if ($xml === false) {
            throw new Exception('Failed to parse eBay XML response');
        }

        $ack = (string) $xml->Ack;
        if ($ack === 'Failure') {
            $errorMessage = isset($xml->Errors->ShortMessage) ? (string) $xml->Errors->ShortMessage : 'Unknown error';
            $longMessage = isset($xml->Errors->LongMessage) ? (string) $xml->Errors->LongMessage : '';
            throw new Exception('eBay API Error: ' . $errorMessage . ' - ' . $longMessage);
        }

        $result = [
            'success' => true,
            'ack' => $ack,
            'list_type' => $listType,
            'items' => [],
            'pagination' => [
                'totalEntries' => 0,
                'totalPages' => 0,
                'pageNumber' => 1,
            ],
        ];

        // Get the list container based on type
        $listContainer = $xml->$listType ?? null;

        if ($listContainer) {
            // Parse pagination
            if (isset($listContainer->PaginationResult)) {
                $result['pagination']['totalEntries'] = (int) $listContainer->PaginationResult->TotalNumberOfEntries;
                $result['pagination']['totalPages'] = (int) $listContainer->PaginationResult->TotalNumberOfPages;
            }

            // Parse items
            if (isset($listContainer->ItemArray->Item)) {
                foreach ($listContainer->ItemArray->Item as $item) {
                    $parsedItem = [
                        'item_id' => (string) $item->ItemID,
                        'title' => (string) $item->Title,
                        'sku' => (string) ($item->SKU ?? ''),
                        'price' => [
                            'value' => (float) ($item->SellingStatus->CurrentPrice ?? $item->BuyItNowPrice ?? $item->StartPrice ?? 0),
                            'currency' => (string) ($item->SellingStatus->CurrentPrice['currencyID'] ?? $item->BuyItNowPrice['currencyID'] ?? 'USD'),
                        ],
                        'quantity' => (int) ($item->Quantity ?? 0),
                        'quantity_available' => (int) ($item->QuantityAvailable ?? $item->Quantity ?? 0),
                        'quantity_sold' => (int) ($item->SellingStatus->QuantitySold ?? 0),
                        'condition' => (string) ($item->ConditionDisplayName ?? ''),
                        'category' => [
                            'id' => (string) ($item->PrimaryCategory->CategoryID ?? ''),
                            'name' => (string) ($item->PrimaryCategory->CategoryName ?? ''),
                        ],
                        'listing_type' => (string) ($item->ListingType ?? ''),
                        'listing_status' => (string) ($item->SellingStatus->ListingStatus ?? 'Draft'),
                        'listing_url' => (string) ($item->ListingDetails->ViewItemURL ?? ''),
                        'start_time' => (string) ($item->ListingDetails->StartTime ?? ''),
                        'end_time' => (string) ($item->ListingDetails->EndTime ?? ''),
                        'images' => [],
                    ];

                    // For sold items, add transaction/order details
                    if ($listType === 'SoldList' && isset($item->SellingStatus)) {
                        $parsedItem['selling_status'] = [
                            'current_price' => (float) ($item->SellingStatus->CurrentPrice ?? 0),
                            'quantity_sold' => (int) ($item->SellingStatus->QuantitySold ?? 0),
                            'bid_count' => (int) ($item->SellingStatus->BidCount ?? 0),
                        ];

                        // Parse transaction details if available
                        if (isset($item->TransactionArray->Transaction)) {
                            $parsedItem['transactions'] = [];
                            foreach ($item->TransactionArray->Transaction as $transaction) {
                                $transactionData = [
                                    'transaction_id' => (string) ($transaction->TransactionID ?? ''),
                                    'transaction_price' => (float) ($transaction->TransactionPrice ?? 0),
                                    'quantity_purchased' => (int) ($transaction->QuantityPurchased ?? 0),
                                    'paid_time' => (string) ($transaction->PaidTime ?? ''),
                                    'shipped_time' => (string) ($transaction->ShippedTime ?? ''),
                                    'order_line_item_id' => (string) ($transaction->OrderLineItemID ?? ''),
                                ];

                                // Buyer info
                                if (isset($transaction->Buyer)) {
                                    $transactionData['buyer'] = [
                                        'user_id' => (string) ($transaction->Buyer->UserID ?? ''),
                                        'email' => (string) ($transaction->Buyer->Email ?? ''),
                                    ];
                                }

                                // Shipping address
                                if (isset($transaction->Buyer->BuyerInfo->ShippingAddress)) {
                                    $addr = $transaction->Buyer->BuyerInfo->ShippingAddress;
                                    $transactionData['shipping_address'] = [
                                        'name' => (string) ($addr->Name ?? ''),
                                        'street1' => (string) ($addr->Street1 ?? ''),
                                        'street2' => (string) ($addr->Street2 ?? ''),
                                        'city' => (string) ($addr->CityName ?? ''),
                                        'state' => (string) ($addr->StateOrProvince ?? ''),
                                        'postal_code' => (string) ($addr->PostalCode ?? ''),
                                        'country' => (string) ($addr->CountryName ?? ''),
                                    ];
                                }

                                $parsedItem['transactions'][] = $transactionData;
                            }
                        }
                    }

                    // Parse images
                    if (isset($item->PictureDetails->PictureURL)) {
                        foreach ($item->PictureDetails->PictureURL as $imageUrl) {
                            $parsedItem['images'][] = (string) $imageUrl;
                        }
                    } elseif (isset($item->PictureDetails->GalleryURL)) {
                        $parsedItem['images'][] = (string) $item->PictureDetails->GalleryURL;
                    }

                    $result['items'][] = $parsedItem;
                }
            }
        }

        $result['total_items'] = count($result['items']);

        return $result;
    }

    /**
     * Get sold listings from eBay using GetMyeBaySelling API
     * Note: eBay limits SoldList to last 60 days max
     */
    public function getSoldListings(SalesChannel $salesChannel, int $page = 1, int $entriesPerPage = 100, int $durationInDays = 60): array
    {
        try {
            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ErrorLanguage>en_US</ErrorLanguage>
    <WarningLevel>High</WarningLevel>
    <SoldList>
        <Include>true</Include>
        <DurationInDays>' . min($durationInDays, 60) . '</DurationInDays>
        <Pagination>
            <EntriesPerPage>' . $entriesPerPage . '</EntriesPerPage>
            <PageNumber>' . $page . '</PageNumber>
        </Pagination>
    </SoldList>
    <DetailLevel>ReturnAll</DetailLevel>
</GetMyeBaySellingRequest>';

            $response = Http::timeout(120)
                ->connectTimeout(30)
                ->withHeaders([
                    'X-EBAY-API-SITEID' => '0',
                    'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                    'X-EBAY-API-CALL-NAME' => 'GetMyeBaySelling',
                    'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                    'Content-Type' => 'text/xml',
                ])
                ->withBody($xmlRequest, 'text/xml')
                ->post('https://api.ebay.com/ws/api.dll');

            Log::info('eBay GetMyeBaySelling (Sold) Response', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay GetMyeBaySelling (Sold) Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay GetMyeBaySelling failed: ' . $response->body());
            }

            return $this->parseGetMyeBaySellingResponse($response->body(), 'SoldList');
        } catch (Exception $e) {
            Log::error('eBay getSoldListings Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Alias for getSoldListings (for backward compatibility)
     */
    public function getCompletedListings(SalesChannel $salesChannel, int $page = 1, int $entriesPerPage = 100): array
    {
        return $this->getSoldListings($salesChannel, $page, $entriesPerPage);
    }

    /**
     * Get ALL sold listings (handles pagination automatically)
     * Note: eBay limits to last 60 days
     */
    public function getAllSoldListings(SalesChannel $salesChannel, int $durationInDays = 60): array
    {
        $allItems = [];
        $page = 1;
        $entriesPerPage = 200;

        do {
            $response = $this->getSoldListings($salesChannel, $page, $entriesPerPage, $durationInDays);
            $allItems = array_merge($allItems, $response['items']);

            $totalPages = $response['pagination']['totalPages'];
            $page++;
        } while ($page <= $totalPages && $totalPages > 0);

        return [
            'success' => true,
            'list_type' => 'SoldList',
            'total_items' => count($allItems),
            'items' => $allItems,
        ];
    }

    /**
     * Alias for getAllSoldListings (for backward compatibility)
     */
    public function getAllCompletedListings(SalesChannel $salesChannel): array
    {
        return $this->getAllSoldListings($salesChannel);
    }

    /**
     * Get ALL unsold listings (handles pagination automatically)
     */
    public function getAllUnsoldListings(SalesChannel $salesChannel, int $durationInDays = 60): array
    {
        $allItems = [];
        $page = 1;
        $entriesPerPage = 200;

        do {
            $response = $this->getUnsoldListings($salesChannel, $page, $entriesPerPage, $durationInDays);
            $allItems = array_merge($allItems, $response['items']);

            $totalPages = $response['pagination']['totalPages'];
            $page++;
        } while ($page <= $totalPages && $totalPages > 0);

        return [
            'success' => true,
            'list_type' => 'UnsoldList',
            'total_items' => count($allItems),
            'items' => $allItems,
        ];
    }

    /**
     * Alias for getAllUnsoldListings (for backward compatibility)
     */
    public function getAllDraftListings(SalesChannel $salesChannel): array
    {
        return $this->getAllUnsoldListings($salesChannel);
    }

    /**
     * Parse XML response from GetSellerList
     */
    private function parseGetSellerListResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);

        if ($xml === false) {
            throw new Exception('Failed to parse eBay XML response');
        }

        // Check for errors
        $ack = (string) $xml->Ack;
        if ($ack === 'Failure') {
            $errorMessage = isset($xml->Errors->ShortMessage) ? (string) $xml->Errors->ShortMessage : 'Unknown error';
            $longMessage = isset($xml->Errors->LongMessage) ? (string) $xml->Errors->LongMessage : '';
            throw new Exception('eBay API Error: ' . $errorMessage . ' - ' . $longMessage);
        }

        $result = [
            'success' => true,
            'ack' => $ack,
            'items' => [],
            'pagination' => [
                'totalEntries' => 0,
                'totalPages' => 0,
                'pageNumber' => (int) ($xml->PageNumber ?? 1),
            ],
        ];

        // Parse pagination
        if (isset($xml->PaginationResult)) {
            $result['pagination']['totalEntries'] = (int) $xml->PaginationResult->TotalNumberOfEntries;
            $result['pagination']['totalPages'] = (int) $xml->PaginationResult->TotalNumberOfPages;
        }

        // Parse items
        if (isset($xml->ItemArray->Item)) {
            foreach ($xml->ItemArray->Item as $item) {
                $parsedItem = [
                    'item_id' => (string) $item->ItemID,
                    'title' => (string) $item->Title,
                    'sku' => (string) ($item->SKU ?? ''),
                    'description' => (string) ($item->Description ?? ''),
                    'price' => [
                        'value' => (float) ($item->SellingStatus->CurrentPrice ?? 0),
                        'currency' => (string) ($item->SellingStatus->CurrentPrice['currencyID'] ?? 'USD'),
                    ],
                    'buy_it_now_price' => [
                        'value' => (float) ($item->BuyItNowPrice ?? 0),
                        'currency' => (string) ($item->BuyItNowPrice['currencyID'] ?? 'USD'),
                    ],
                    'start_price' => [
                        'value' => (float) ($item->StartPrice ?? 0),
                        'currency' => (string) ($item->StartPrice['currencyID'] ?? 'USD'),
                    ],
                    'quantity' => (int) ($item->Quantity ?? 0),
                    'quantity_available' => (int) ($item->QuantityAvailable ?? 0),
                    'quantity_sold' => (int) ($item->SellingStatus->QuantitySold ?? 0),
                    'condition_id' => (string) ($item->ConditionID ?? ''),
                    'condition' => (string) ($item->ConditionDisplayName ?? ''),
                    'condition_description' => (string) ($item->ConditionDescription ?? ''),
                    'category' => [
                        'id' => (string) ($item->PrimaryCategory->CategoryID ?? ''),
                        'name' => (string) ($item->PrimaryCategory->CategoryName ?? ''),
                    ],
                    'secondary_category' => [
                        'id' => (string) ($item->SecondaryCategory->CategoryID ?? ''),
                        'name' => (string) ($item->SecondaryCategory->CategoryName ?? ''),
                    ],
                    'listing_type' => (string) ($item->ListingType ?? ''),
                    'listing_status' => (string) ($item->SellingStatus->ListingStatus ?? ''),
                    'listing_url' => (string) ($item->ListingDetails->ViewItemURL ?? ''),
                    'listing_duration' => (string) ($item->ListingDuration ?? ''),
                    'start_time' => (string) ($item->ListingDetails->StartTime ?? ''),
                    'end_time' => (string) ($item->ListingDetails->EndTime ?? ''),
                    'watch_count' => (int) ($item->WatchCount ?? 0),
                    'hit_count' => (int) ($item->HitCount ?? 0),
                    'location' => (string) ($item->Location ?? ''),
                    'country' => (string) ($item->Country ?? ''),
                    'postal_code' => (string) ($item->PostalCode ?? ''),
                    'images' => [],
                    'gallery_url' => (string) ($item->PictureDetails->GalleryURL ?? ''),
                    'item_specifics' => [],
                    'variations' => [],
                    'shipping_details' => [],
                    'return_policy' => [],
                ];

                // Parse images
                if (isset($item->PictureDetails->PictureURL)) {
                    foreach ($item->PictureDetails->PictureURL as $imageUrl) {
                        $parsedItem['images'][] = (string) $imageUrl;
                    }
                }

                // Parse item specifics (attributes like Brand, MPN, etc.)
                if (isset($item->ItemSpecifics->NameValueList)) {
                    foreach ($item->ItemSpecifics->NameValueList as $specific) {
                        $name = (string) $specific->Name;
                        $values = [];
                        if (isset($specific->Value)) {
                            foreach ($specific->Value as $value) {
                                $values[] = (string) $value;
                            }
                        }
                        $parsedItem['item_specifics'][$name] = count($values) === 1 ? $values[0] : $values;
                    }
                }

                // Parse variations (for multi-variation listings)
                if (isset($item->Variations->Variation)) {
                    foreach ($item->Variations->Variation as $variation) {
                        $variationData = [
                            'sku' => (string) ($variation->SKU ?? ''),
                            'quantity' => (int) ($variation->Quantity ?? 0),
                            'quantity_sold' => (int) ($variation->SellingStatus->QuantitySold ?? 0),
                            'price' => (float) ($variation->StartPrice ?? 0),
                            'specifics' => [],
                        ];

                        if (isset($variation->VariationSpecifics->NameValueList)) {
                            foreach ($variation->VariationSpecifics->NameValueList as $specific) {
                                $variationData['specifics'][(string) $specific->Name] = (string) ($specific->Value ?? '');
                            }
                        }

                        $parsedItem['variations'][] = $variationData;
                    }
                }

                // Parse shipping details
                if (isset($item->ShippingDetails)) {
                    $parsedItem['shipping_details'] = [
                        'shipping_type' => (string) ($item->ShippingDetails->ShippingType ?? ''),
                        'global_shipping' => (string) ($item->ShippingDetails->GlobalShipping ?? 'false') === 'true',
                    ];

                    // Parse shipping service options
                    if (isset($item->ShippingDetails->ShippingServiceOptions)) {
                        $parsedItem['shipping_details']['services'] = [];
                        foreach ($item->ShippingDetails->ShippingServiceOptions as $service) {
                            $parsedItem['shipping_details']['services'][] = [
                                'service' => (string) ($service->ShippingService ?? ''),
                                'cost' => (float) ($service->ShippingServiceCost ?? 0),
                                'free_shipping' => (string) ($service->FreeShipping ?? 'false') === 'true',
                            ];
                        }
                    }
                }

                // Parse return policy
                if (isset($item->ReturnPolicy)) {
                    $parsedItem['return_policy'] = [
                        'returns_accepted' => (string) ($item->ReturnPolicy->ReturnsAcceptedOption ?? ''),
                        'returns_within' => (string) ($item->ReturnPolicy->ReturnsWithinOption ?? ''),
                        'refund' => (string) ($item->ReturnPolicy->RefundOption ?? ''),
                        'shipping_cost_paid_by' => (string) ($item->ReturnPolicy->ShippingCostPaidByOption ?? ''),
                    ];
                }

                $result['items'][] = $parsedItem;
            }
        }

        $result['total_items'] = count($result['items']);

        return $result;
    }

    /**
     * Get ALL active listings (handles pagination automatically)
     */
    public function getAllActiveSellerListings(SalesChannel $salesChannel): array
    {
        $allItems = [];
        $page = 1;
        $entriesPerPage = 200; // Max allowed by eBay

        do {
            $response = $this->getActiveSellerListings($salesChannel, $page, $entriesPerPage);
            $allItems = array_merge($allItems, $response['items']);

            $totalPages = $response['pagination']['totalPages'];
            $page++;

            Log::info('eBay GetSellerList Pagination', [
                'page' => $page - 1,
                'totalPages' => $totalPages,
                'itemsFetched' => count($response['items']),
                'totalItemsSoFar' => count($allItems),
            ]);
        } while ($page <= $totalPages);

        return [
            'success' => true,
            'total_items' => count($allItems),
            'items' => $allItems,
        ];
    }

    /**
     * Get single item details using GetItem API
     */
    public function getItemDetails(SalesChannel $salesChannel, string $itemId): array
    {
        try {
            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
<GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ErrorLanguage>en_US</ErrorLanguage>
    <WarningLevel>High</WarningLevel>
    <DetailLevel>ReturnAll</DetailLevel>
    <ItemID>' . $itemId . '</ItemID>
    <IncludeItemSpecifics>true</IncludeItemSpecifics>
    <IncludeWatchCount>true</IncludeWatchCount>
</GetItemRequest>';

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withHeaders([
                    'X-EBAY-API-SITEID' => '0',
                    'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                    'X-EBAY-API-CALL-NAME' => 'GetItem',
                    'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                    'Content-Type' => 'text/xml',
                ])
                ->withBody($xmlRequest, 'text/xml')
                ->post('https://api.ebay.com/ws/api.dll');

            Log::info('eBay GetItem Response', [
                'status' => $response->status(),
                'item_id' => $itemId,
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                throw new Exception('eBay GetItem failed: ' . $response->body());
            }

            $xml = simplexml_load_string($response->body());

            if ($xml === false) {
                throw new Exception('Failed to parse eBay XML response');
            }

            $ack = (string) $xml->Ack;
            if ($ack === 'Failure') {
                $errorMessage = isset($xml->Errors->ShortMessage) ? (string) $xml->Errors->ShortMessage : 'Unknown error';
                throw new Exception('eBay API Error: ' . $errorMessage);
            }

            $item = $xml->Item;

            return [
                'success' => true,
                'item' => [
                    'item_id' => (string) $item->ItemID,
                    'title' => (string) $item->Title,
                    'sku' => (string) ($item->SKU ?? ''),
                    'description' => (string) ($item->Description ?? ''),
                    'price' => [
                        'value' => (float) ($item->SellingStatus->CurrentPrice ?? 0),
                        'currency' => (string) ($item->SellingStatus->CurrentPrice['currencyID'] ?? 'USD'),
                    ],
                    'quantity' => (int) ($item->Quantity ?? 0),
                    'quantity_available' => (int) ($item->QuantityAvailable ?? 0),
                    'quantity_sold' => (int) ($item->SellingStatus->QuantitySold ?? 0),
                    'condition_id' => (string) ($item->ConditionID ?? ''),
                    'condition' => (string) ($item->ConditionDisplayName ?? ''),
                    'category' => [
                        'id' => (string) ($item->PrimaryCategory->CategoryID ?? ''),
                        'name' => (string) ($item->PrimaryCategory->CategoryName ?? ''),
                    ],
                    'listing_url' => (string) ($item->ListingDetails->ViewItemURL ?? ''),
                    'images' => $this->parseImages($item),
                    'item_specifics' => $this->parseItemSpecifics($item),
                ],
            ];
        } catch (Exception $e) {
            Log::error('eBay getItemDetails Error', [
                'message' => $e->getMessage(),
                'item_id' => $itemId,
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Helper to parse images from item
     */
    private function parseImages($item): array
    {
        $images = [];
        if (isset($item->PictureDetails->PictureURL)) {
            foreach ($item->PictureDetails->PictureURL as $imageUrl) {
                $images[] = (string) $imageUrl;
            }
        }
        return $images;
    }

    /**
     * Helper to parse item specifics
     */
    private function parseItemSpecifics($item): array
    {
        $specifics = [];
        if (isset($item->ItemSpecifics->NameValueList)) {
            foreach ($item->ItemSpecifics->NameValueList as $specific) {
                $name = (string) $specific->Name;
                $values = [];
                if (isset($specific->Value)) {
                    foreach ($specific->Value as $value) {
                        $values[] = (string) $value;
                    }
                }
                $specifics[$name] = count($values) === 1 ? $values[0] : $values;
            }
        }
        return $specifics;
    }

    /**
     * Get inventory items using Inventory API (for SKU-based inventory)
     * Note: This only returns items created via the Inventory API
     */
    public function getInventoryItems(SalesChannel $salesChannel, int $limit = 100, int $offset = 0): array
    {
        try {
            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $salesChannel->access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get('https://api.ebay.com/sell/inventory/v1/inventory_item', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            Log::info('eBay Get Inventory Items Response', [
                'status' => $response->status(),
                'limit' => $limit,
                'offset' => $offset,
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay Get Inventory Items Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay get inventory items failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getInventoryItems Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Get active listings/offers from eBay
     */
    public function getActiveListings(SalesChannel $salesChannel, int $limit = 100, int $offset = 0): array
    {
        try {
            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $salesChannel->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->get('https://api.ebay.com/sell/inventory/v1/offer', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            Log::info('eBay Get Active Listings Response', [
                'status' => $response->status(),
                'limit' => $limit,
                'offset' => $offset,
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay Get Active Listings Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay get active listings failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getActiveListings Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Get all listings from eBay with optional status filter
     * Status options: ALL, PUBLISHED, UNPUBLISHED, ENDED
     */
    public function getAllListings(SalesChannel $salesChannel, string $status = 'ALL', int $limit = 100, int $offset = 0): array
    {
        try {
            $params = [
                'limit' => $limit,
                'offset' => $offset,
            ];

            if ($status !== 'ALL') {
                $params['status'] = $status;
            }

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $salesChannel->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->get('https://api.ebay.com/sell/inventory/v1/offer', $params);

            Log::info('eBay Get All Listings Response', [
                'status' => $response->status(),
                'filter_status' => $status,
                'limit' => $limit,
                'offset' => $offset,
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay Get All Listings Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay get all listings failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getAllListings Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Generate OAuth authorization URL for user consent
     */
    public function getAuthorizationUrl(SalesChannel $salesChannel, string $state = null): string
    {
        $state = $state ?? bin2hex(random_bytes(16));

        $params = [
            'client_id' => $salesChannel->client_id,
            'redirect_uri' => $salesChannel->ru_name,
            'response_type' => 'code',
            'scope' => $salesChannel->user_scopes,
            'state' => $state,
        ];

        return 'https://auth.ebay.com/oauth2/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getUserAccessToken(SalesChannel $salesChannel, string $code): array
    {
        try {
            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($salesChannel->client_id . ':' . $salesChannel->client_secret),
                ])
                ->asForm()
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $salesChannel->ru_name,
                ]);

            Log::info('eBay User Token Response', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay User Token Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay user token exchange failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getUserAccessToken Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }
}
