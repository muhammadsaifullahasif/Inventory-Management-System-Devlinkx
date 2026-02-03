<?php

namespace App\Services;

use App\Models\SalesChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EbayService
{
    private const EBAY_API_URL = 'https://api.ebay.com/ws/api.dll';
    private const EBAY_TOKEN_URL = 'https://api.ebay.com/identity/v1/oauth2/token';
    private const API_COMPATIBILITY_LEVEL = '967';
    private const API_SITE_ID = '0'; // US

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
     * Get active listings (paginated)
     */
    public function getActiveListings(SalesChannel $salesChannel, int $page = 1, int $perPage = 100): array
    {
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
        return $this->parseActiveListingsResponse($response);
    }

    /**
     * Get ALL active listings (auto-pagination)
     */
    public function getAllActiveListings(SalesChannel $salesChannel): array
    {
        $allItems = [];
        $page = 1;

        do {
            $response = $this->getActiveListings($salesChannel, $page, 200);
            $allItems = array_merge($allItems, $response['items']);
            $totalPages = $response['pagination']['totalPages'];
            $page++;
        } while ($page <= $totalPages);

        return [
            'success' => true,
            'total_items' => count($allItems),
            'items' => $allItems,
        ];
    }

    /**
     * Get unsold listings (paginated)
     */
    public function getUnsoldListings(SalesChannel $salesChannel, int $page = 1, int $perPage = 100, int $days = 60): array
    {
        $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
            <GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                <UnsoldList>
                    <Include>true</Include>
                    <DurationInDays>' . $days . '</DurationInDays>
                    <Pagination>
                        <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                        <PageNumber>' . $page . '</PageNumber>
                    </Pagination>
                </UnsoldList>
                <DetailLevel>ReturnAll</DetailLevel>
            </GetMyeBaySellingRequest>';

        $response = $this->callTradingApi($salesChannel, 'GetMyeBaySelling', $xmlRequest);
        return $this->parseUnsoldListingsResponse($response);
    }

    /**
     * Get ALL unsold listings (auto-pagination)
     */
    public function getAllUnsoldListings(SalesChannel $salesChannel, int $days = 60): array
    {
        $allItems = [];
        $page = 1;

        do {
            $response = $this->getUnsoldListings($salesChannel, $page, 200, $days);
            $allItems = array_merge($allItems, $response['items']);
            $totalPages = $response['pagination']['totalPages'];
            $page++;
        } while ($page <= $totalPages && $totalPages > 0);

        return [
            'success' => true,
            'total_items' => count($allItems),
            'items' => $allItems,
        ];
    }

    /**
     * Get single item details
     */
    public function getItemDetails(SalesChannel $salesChannel, string $itemId): array
    {
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
        return $this->parseItemDetailsResponse($response);
    }

    /**
     * Generate OAuth authorization URL
     */
    public function getAuthorizationUrl(SalesChannel $salesChannel, string $state = null): string
    {
        $state = $state ?? bin2hex(random_bytes(16));

        return 'https://auth.ebay.com/oauth2/authorize?' . http_build_query([
            'client_id' => $salesChannel->client_id,
            'redirect_uri' => $salesChannel->ru_name,
            'response_type' => 'code',
            'scope' => $salesChannel->user_scopes,
            'state' => $state,
        ]);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getUserAccessToken(SalesChannel $salesChannel, string $code): array
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
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $salesChannel->ru_name,
            ]);

        if ($response->failed()) {
            throw new Exception('eBay user token exchange failed: ' . $response->body());
        }

        return $response->json();
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
     * Parse unsold listings response
     */
    private function parseUnsoldListingsResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);
        $this->checkForErrors($xml);

        $result = [
            'success' => true,
            'items' => [],
            'pagination' => [
                'totalEntries' => 0,
                'totalPages' => 0,
                'pageNumber' => 1,
            ],
        ];

        if (isset($xml->UnsoldList)) {
            $list = $xml->UnsoldList;

            if (isset($list->PaginationResult)) {
                $result['pagination']['totalEntries'] = (int) $list->PaginationResult->TotalNumberOfEntries;
                $result['pagination']['totalPages'] = (int) $list->PaginationResult->TotalNumberOfPages;
            }

            if (isset($list->ItemArray->Item)) {
                foreach ($list->ItemArray->Item as $item) {
                    $result['items'][] = $this->parseItem($item, false);
                }
            }
        }

        $result['total_items'] = count($result['items']);
        return $result;
    }

    /**
     * Parse item details response
     */
    private function parseItemDetailsResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);
        $this->checkForErrors($xml);

        return [
            'success' => true,
            'item' => $this->parseItem($xml->Item, true),
        ];
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
     * Add a fixed price item (create new listing)
     */
    public function addFixedPriceItem(SalesChannel $salesChannel, array $itemData): array
    {
        $xmlRequest = $this->buildAddFixedPriceItemXml($itemData);
        $xmlResponse = $this->callTradingApi($salesChannel, 'AddFixedPriceItem', $xmlRequest);

        return $this->parseAddItemResponse($xmlResponse);
    }

    /**
     * Revise a fixed price item (update existing listing)
     */
    public function reviseFixedPriceItem(SalesChannel $salesChannel, string $itemId, array $itemData): array
    {
        $xmlRequest = $this->buildReviseFixedPriceItemXml($itemId, $itemData);
        $xmlResponse = $this->callTradingApi($salesChannel, 'ReviseFixedPriceItem', $xmlRequest);

        return $this->parseReviseItemResponse($xmlResponse);
    }

    /**
     * End a fixed price item (end/deactivate listing)
     */
    public function endFixedPriceItem(SalesChannel $salesChannel, string $itemId, string $endingReason = 'NotAvailable'): array
    {
        $xmlRequest = $this->buildEndFixedPriceItemXml($itemId, $endingReason);
        $xmlResponse = $this->callTradingApi($salesChannel, 'EndFixedPriceItem', $xmlRequest);

        return $this->parseEndItemResponse($xmlResponse);
    }

    /**
     * Relist a fixed price item (reactivate ended listing)
     */
    public function relistFixedPriceItem(SalesChannel $salesChannel, string $itemId, array $itemData = []): array
    {
        $xmlRequest = $this->buildRelistFixedPriceItemXml($itemId, $itemData);
        $xmlResponse = $this->callTradingApi($salesChannel, 'RelistFixedPriceItem', $xmlRequest);

        return $this->parseRelistItemResponse($xmlResponse);
    }

    /**
     * Find listing by SKU
     */
    public function findListingBySku(SalesChannel $salesChannel, string $sku): ?array
    {
        $xmlRequest = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <RequesterCredentials>
        <eBayAuthToken>{$salesChannel->access_token}</eBayAuthToken>
    </RequesterCredentials>
    <SKUArray>
        <SKU>{$this->escapeXml($sku)}</SKU>
    </SKUArray>
    <EndTimeFrom>{$this->getDateYearsAgo(1)}</EndTimeFrom>
    <EndTimeTo>{$this->getDateYearsAhead(1)}</EndTimeTo>
    <IncludeVariations>true</IncludeVariations>
    <Pagination>
        <EntriesPerPage>10</EntriesPerPage>
        <PageNumber>1</PageNumber>
    </Pagination>
    <DetailLevel>ReturnAll</DetailLevel>
</GetSellerListRequest>
XML;

        try {
            $xmlResponse = $this->callTradingApi($salesChannel, 'GetSellerList', $xmlRequest);
            $xml = simplexml_load_string($xmlResponse);

            if ((string) $xml->Ack === 'Failure') {
                return null;
            }

            if (isset($xml->ItemArray->Item)) {
                foreach ($xml->ItemArray->Item as $item) {
                    $itemSku = (string) ($item->SKU ?? '');
                    if (strtoupper($itemSku) === strtoupper($sku)) {
                        return [
                            'ItemID' => (string) $item->ItemID,
                            'Title' => (string) $item->Title,
                            'SKU' => $itemSku,
                            'ListingStatus' => (string) ($item->SellingStatus->ListingStatus ?? ''),
                            'CurrentPrice' => (float) ($item->SellingStatus->CurrentPrice ?? 0),
                            'Quantity' => (int) ($item->Quantity ?? 0),
                            'QuantityAvailable' => (int) ($item->QuantityAvailable ?? 0),
                        ];
                    }
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error('Failed to find listing by SKU', ['sku' => $sku, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Build XML for AddFixedPriceItem
     */
    private function buildAddFixedPriceItemXml(array $data): string
    {
        $title = $this->escapeXml($data['title'] ?? '');
        $description = $this->escapeXml($data['description'] ?? $data['title'] ?? '');
        $sku = $this->escapeXml($data['sku'] ?? '');
        $price = number_format((float) ($data['price'] ?? 0), 2, '.', '');
        $quantity = (int) ($data['quantity'] ?? 1);
        $categoryId = $this->escapeXml($data['category_id'] ?? '');
        $conditionId = (int) ($data['condition_id'] ?? 1000); // 1000 = New
        $listingDuration = $data['listing_duration'] ?? 'GTC';
        $currency = $data['currency'] ?? 'USD';
        $country = $data['country'] ?? 'US';
        $location = $this->escapeXml($data['location'] ?? 'United States');
        $postalCode = $this->escapeXml($data['postal_code'] ?? '');

        // Return policy defaults
        $returnsAccepted = $data['returns_accepted'] ?? 'ReturnsAccepted';
        $returnWithin = $data['return_within'] ?? 'Days_30';
        $refundOption = $data['refund_option'] ?? 'MoneyBackOrReplacement';
        $shippingCostPaidBy = $data['shipping_cost_paid_by'] ?? 'Buyer';

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<AddFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <Item>
        <Title>{$title}</Title>
        <Description><![CDATA[{$description}]]></Description>
        <SKU>{$sku}</SKU>
        <PrimaryCategory>
            <CategoryID>{$categoryId}</CategoryID>
        </PrimaryCategory>
        <StartPrice currencyID="{$currency}">{$price}</StartPrice>
        <ConditionID>{$conditionId}</ConditionID>
        <Country>{$country}</Country>
        <Currency>{$currency}</Currency>
        <DispatchTimeMax>3</DispatchTimeMax>
        <ListingDuration>{$listingDuration}</ListingDuration>
        <ListingType>FixedPriceItem</ListingType>
        <Location>{$location}</Location>
        <PostalCode>{$postalCode}</PostalCode>
        <Quantity>{$quantity}</Quantity>
        <ReturnPolicy>
            <ReturnsAcceptedOption>{$returnsAccepted}</ReturnsAcceptedOption>
            <ReturnsWithinOption>{$returnWithin}</ReturnsWithinOption>
            <RefundOption>{$refundOption}</RefundOption>
            <ShippingCostPaidByOption>{$shippingCostPaidBy}</ShippingCostPaidByOption>
        </ReturnPolicy>
        <ShippingDetails>
            <ShippingType>Flat</ShippingType>
            <ShippingServiceOptions>
                <FreeShipping>true</FreeShipping>
                <ShippingService>USPSParcel</ShippingService>
                <ShippingServicePriority>1</ShippingServicePriority>
            </ShippingServiceOptions>
        </ShippingDetails>
XML;

        // Add picture URLs if provided
        if (!empty($data['picture_urls'])) {
            $xml .= "\n        <PictureDetails>";
            foreach ((array) $data['picture_urls'] as $url) {
                $xml .= "\n            <PictureURL>" . $this->escapeXml($url) . "</PictureURL>";
            }
            $xml .= "\n        </PictureDetails>";
        }

        // Add item specifics if provided
        if (!empty($data['item_specifics'])) {
            $xml .= "\n        <ItemSpecifics>";
            foreach ($data['item_specifics'] as $name => $value) {
                $xml .= <<<SPEC

            <NameValueList>
                <Name>{$this->escapeXml($name)}</Name>
                <Value>{$this->escapeXml($value)}</Value>
            </NameValueList>
SPEC;
            }
            $xml .= "\n        </ItemSpecifics>";
        }

        $xml .= "\n    </Item>\n</AddFixedPriceItemRequest>";

        return $xml;
    }

    /**
     * Build XML for ReviseFixedPriceItem
     */
    private function buildReviseFixedPriceItemXml(string $itemId, array $data): string
    {
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <Item>
        <ItemID>{$itemId}</ItemID>
XML;

        if (isset($data['title'])) {
            $xml .= "\n        <Title>{$this->escapeXml($data['title'])}</Title>";
        }

        if (isset($data['description'])) {
            $xml .= "\n        <Description><![CDATA[{$data['description']}]]></Description>";
        }

        if (isset($data['price'])) {
            $currency = $data['currency'] ?? 'USD';
            $price = number_format((float) $data['price'], 2, '.', '');
            $xml .= "\n        <StartPrice currencyID=\"{$currency}\">{$price}</StartPrice>";
        }

        if (isset($data['quantity'])) {
            $xml .= "\n        <Quantity>{$data['quantity']}</Quantity>";
        }

        if (isset($data['sku'])) {
            $xml .= "\n        <SKU>{$this->escapeXml($data['sku'])}</SKU>";
        }

        // Add picture URLs if provided
        if (!empty($data['picture_urls'])) {
            $xml .= "\n        <PictureDetails>";
            foreach ((array) $data['picture_urls'] as $url) {
                $xml .= "\n            <PictureURL>" . $this->escapeXml($url) . "</PictureURL>";
            }
            $xml .= "\n        </PictureDetails>";
        }

        $xml .= "\n    </Item>\n</ReviseFixedPriceItemRequest>";

        return $xml;
    }

    /**
     * Build XML for EndFixedPriceItem
     */
    private function buildEndFixedPriceItemXml(string $itemId, string $endingReason): string
    {
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<EndFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ItemID>{$itemId}</ItemID>
    <EndingReason>{$endingReason}</EndingReason>
</EndFixedPriceItemRequest>
XML;
    }

    /**
     * Build XML for RelistFixedPriceItem
     */
    private function buildRelistFixedPriceItemXml(string $itemId, array $data): string
    {
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<RelistFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <Item>
        <ItemID>{$itemId}</ItemID>
XML;

        if (isset($data['title'])) {
            $xml .= "\n        <Title>{$this->escapeXml($data['title'])}</Title>";
        }

        if (isset($data['price'])) {
            $currency = $data['currency'] ?? 'USD';
            $price = number_format((float) $data['price'], 2, '.', '');
            $xml .= "\n        <StartPrice currencyID=\"{$currency}\">{$price}</StartPrice>";
        }

        if (isset($data['quantity'])) {
            $xml .= "\n        <Quantity>{$data['quantity']}</Quantity>";
        }

        $xml .= "\n    </Item>\n</RelistFixedPriceItemRequest>";

        return $xml;
    }

    /**
     * Parse AddFixedPriceItem response
     */
    private function parseAddItemResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);

        $result = [
            'success' => in_array((string) $xml->Ack, ['Success', 'Warning']),
            'item_id' => (string) ($xml->ItemID ?? ''),
            'start_time' => (string) ($xml->StartTime ?? ''),
            'end_time' => (string) ($xml->EndTime ?? ''),
            'fees' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if (isset($xml->Fees->Fee)) {
            foreach ($xml->Fees->Fee as $fee) {
                $result['fees'][(string) $fee->Name] = (float) $fee->Fee;
            }
        }

        if (isset($xml->Errors)) {
            foreach ($xml->Errors as $error) {
                $errorData = [
                    'code' => (string) ($error->ErrorCode ?? ''),
                    'short_message' => (string) ($error->ShortMessage ?? ''),
                    'long_message' => (string) ($error->LongMessage ?? ''),
                    'severity' => (string) ($error->SeverityCode ?? ''),
                ];

                if ((string) $error->SeverityCode === 'Warning') {
                    $result['warnings'][] = $errorData;
                } else {
                    $result['errors'][] = $errorData;
                }
            }
        }

        if ($result['success'] && !empty($result['item_id'])) {
            $result['listing_url'] = "https://www.ebay.com/itm/{$result['item_id']}";
        }

        return $result;
    }

    /**
     * Parse ReviseFixedPriceItem response
     */
    private function parseReviseItemResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);

        $result = [
            'success' => in_array((string) $xml->Ack, ['Success', 'Warning']),
            'item_id' => (string) ($xml->ItemID ?? ''),
            'start_time' => (string) ($xml->StartTime ?? ''),
            'end_time' => (string) ($xml->EndTime ?? ''),
            'fees' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if (isset($xml->Fees->Fee)) {
            foreach ($xml->Fees->Fee as $fee) {
                $result['fees'][(string) $fee->Name] = (float) $fee->Fee;
            }
        }

        if (isset($xml->Errors)) {
            foreach ($xml->Errors as $error) {
                $errorData = [
                    'code' => (string) ($error->ErrorCode ?? ''),
                    'short_message' => (string) ($error->ShortMessage ?? ''),
                    'long_message' => (string) ($error->LongMessage ?? ''),
                    'severity' => (string) ($error->SeverityCode ?? ''),
                ];

                if ((string) $error->SeverityCode === 'Warning') {
                    $result['warnings'][] = $errorData;
                } else {
                    $result['errors'][] = $errorData;
                }
            }
        }

        if ($result['success'] && !empty($result['item_id'])) {
            $result['listing_url'] = "https://www.ebay.com/itm/{$result['item_id']}";
        }

        return $result;
    }

    /**
     * Parse EndFixedPriceItem response
     */
    private function parseEndItemResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);

        return [
            'success' => in_array((string) $xml->Ack, ['Success', 'Warning']),
            'end_time' => (string) ($xml->EndTime ?? ''),
            'errors' => $this->extractErrors($xml),
        ];
    }

    /**
     * Parse RelistFixedPriceItem response
     */
    private function parseRelistItemResponse(string $xmlResponse): array
    {
        return $this->parseAddItemResponse($xmlResponse);
    }

    /**
     * Extract errors from XML response
     */
    private function extractErrors($xml): array
    {
        $errors = [];
        if (isset($xml->Errors)) {
            foreach ($xml->Errors as $error) {
                $errors[] = [
                    'code' => (string) ($error->ErrorCode ?? ''),
                    'short_message' => (string) ($error->ShortMessage ?? ''),
                    'long_message' => (string) ($error->LongMessage ?? ''),
                    'severity' => (string) ($error->SeverityCode ?? ''),
                ];
            }
        }
        return $errors;
    }

    /**
     * Escape XML special characters
     */
    private function escapeXml(string $string): string
    {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get date years ago in ISO format
     */
    private function getDateYearsAgo(int $years): string
    {
        return now()->subYears($years)->toIso8601String();
    }

    /**
     * Get date years ahead in ISO format
     */
    private function getDateYearsAhead(int $years): string
    {
        return now()->addYears($years)->toIso8601String();
    }
}
