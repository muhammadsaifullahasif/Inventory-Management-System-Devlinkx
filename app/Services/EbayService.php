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
     * Get all active listings from eBay using Trading API (GetMyeBaySelling)
     * This returns all your active listings in your eBay store
     */
    public function getActiveSellerListings(SalesChannel $salesChannel, int $page = 1, int $entriesPerPage = 100): array
    {
        try {
            $xmlRequest = $this->buildGetMyeBaySellingRequest($page, $entriesPerPage);

            $response = Http::timeout(120)
                ->connectTimeout(30)
                ->withHeaders([
                    'X-EBAY-API-SITEID' => '0', // 0 = US
                    'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                    'X-EBAY-API-CALL-NAME' => 'GetMyeBaySelling',
                    'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                    'Content-Type' => 'text/xml',
                ])
                ->withBody($xmlRequest, 'text/xml')
                ->post('https://api.ebay.com/ws/api.dll');

            Log::info('eBay GetMyeBaySelling Response', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay GetMyeBaySelling Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay GetMyeBaySelling failed: ' . $response->body());
            }

            return $this->parseGetMyeBaySellingResponse($response->body());
        } catch (Exception $e) {
            Log::error('eBay getActiveSellerListings Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Build XML request for GetMyeBaySelling
     */
    private function buildGetMyeBaySellingRequest(int $page, int $entriesPerPage): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ErrorLanguage>en_US</ErrorLanguage>
    <WarningLevel>High</WarningLevel>
    <ActiveList>
        <Include>true</Include>
        <Pagination>
            <EntriesPerPage>' . $entriesPerPage . '</EntriesPerPage>
            <PageNumber>' . $page . '</PageNumber>
        </Pagination>
        <Sort>TimeLeft</Sort>
    </ActiveList>
    <DetailLevel>ReturnAll</DetailLevel>
    <OutputSelector>ActiveList.ItemArray.Item.ItemID</OutputSelector>
    <OutputSelector>ActiveList.ItemArray.Item.Title</OutputSelector>
    <OutputSelector>ActiveList.ItemArray.Item.SellingStatus.CurrentPrice</OutputSelector>
    <OutputSelector>ActiveList.ItemArray.Item.Quantity</OutputSelector>
    <OutputSelector>ActiveList.ItemArray.Item.QuantityAvailable</OutputSelector>
    <OutputSelector>ActiveList.ItemArray.Item.PictureDetails</OutputSelector>
    <OutputSelector>ActiveList.ItemArray.Item.ListingDetails</OutputSelector>
    <OutputSelector>ActiveList.ItemArray.Item.SKU</OutputSelector>
    <OutputSelector>ActiveList.ItemArray.Item.ConditionDisplayName</OutputSelector>
    <OutputSelector>ActiveList.ItemArray.Item.PrimaryCategory</OutputSelector>
    <OutputSelector>ActiveList.PaginationResult</OutputSelector>
</GetMyeBaySellingRequest>';
    }

    /**
     * Parse XML response from GetMyeBaySelling
     */
    private function parseGetMyeBaySellingResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);

        if ($xml === false) {
            throw new Exception('Failed to parse eBay XML response');
        }

        // Check for errors
        $ack = (string) $xml->Ack;
        if ($ack === 'Failure') {
            $errorMessage = (string) $xml->Errors->ShortMessage ?? 'Unknown error';
            throw new Exception('eBay API Error: ' . $errorMessage);
        }

        $result = [
            'success' => true,
            'ack' => $ack,
            'items' => [],
            'pagination' => [
                'totalEntries' => 0,
                'totalPages' => 0,
                'pageNumber' => 1,
            ],
        ];

        // Parse pagination
        if (isset($xml->ActiveList->PaginationResult)) {
            $result['pagination']['totalEntries'] = (int) $xml->ActiveList->PaginationResult->TotalNumberOfEntries;
            $result['pagination']['totalPages'] = (int) $xml->ActiveList->PaginationResult->TotalNumberOfPages;
        }

        // Parse items
        if (isset($xml->ActiveList->ItemArray->Item)) {
            foreach ($xml->ActiveList->ItemArray->Item as $item) {
                $parsedItem = [
                    'item_id' => (string) $item->ItemID,
                    'title' => (string) $item->Title,
                    'sku' => (string) ($item->SKU ?? ''),
                    'price' => [
                        'value' => (float) ($item->SellingStatus->CurrentPrice ?? 0),
                        'currency' => (string) ($item->SellingStatus->CurrentPrice['currencyID'] ?? 'USD'),
                    ],
                    'quantity' => (int) ($item->Quantity ?? 0),
                    'quantity_available' => (int) ($item->QuantityAvailable ?? 0),
                    'condition' => (string) ($item->ConditionDisplayName ?? ''),
                    'category' => [
                        'id' => (string) ($item->PrimaryCategory->CategoryID ?? ''),
                        'name' => (string) ($item->PrimaryCategory->CategoryName ?? ''),
                    ],
                    'listing_url' => (string) ($item->ListingDetails->ViewItemURL ?? ''),
                    'images' => [],
                ];

                // Parse images
                if (isset($item->PictureDetails->PictureURL)) {
                    foreach ($item->PictureDetails->PictureURL as $imageUrl) {
                        $parsedItem['images'][] = (string) $imageUrl;
                    }
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
        } while ($page <= $totalPages);

        return [
            'success' => true,
            'total_items' => count($allItems),
            'items' => $allItems,
        ];
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
