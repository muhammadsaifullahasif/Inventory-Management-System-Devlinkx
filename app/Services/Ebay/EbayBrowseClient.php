<?php

namespace App\Services\Ebay;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for eBay's Browse API (buy/browse) — searches active listings.
 *
 * Uses the base api_scope only, so it works with the standard application
 * token (no restricted-API approval needed, unlike Marketplace Insights).
 * Authenticates with an application (client_credentials) token via
 * EbayApiClient — this queries public active-listing data, not any single
 * seller's account, so it is not tied to a SalesChannel.
 *
 * Usage:
 *   $client = app(EbayBrowseClient::class);
 *   $items = $client->searchActiveListings('Widget Pro 500', 50);
 */
class EbayBrowseClient
{
    public const BASE_URL = 'https://api.ebay.com/buy/browse/v1';

    private const DEFAULT_TIMEOUT = 60;
    private const DEFAULT_CONNECT_TIMEOUT = 30;
    private const DEFAULT_LIMIT = 50;

    public function __construct(protected EbayApiClient $apiClient)
    {
    }

    /**
     * Search active listings for a keyword. Requests the EXTENDED fieldgroup
     * so each item carries estimatedAvailabilities.estimatedSoldQuantity,
     * used to rank competitors by sales volume rather than just price.
     *
     * @return array<int, array> raw itemSummaries entries from eBay
     */
    public function searchActiveListings(string $keyword, int $limit = self::DEFAULT_LIMIT): array
    {
        $token = $this->apiClient->getApplicationToken();

        $response = Http::timeout(self::DEFAULT_TIMEOUT)
            ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
            ->withToken($token)
            ->withHeaders([
                'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US',
            ])
            ->get(self::BASE_URL . '/item_summary/search', [
                'q' => $keyword,
                'filter' => 'buyingOptions:{FIXED_PRICE},itemLocationCountry:US',
                'fieldgroups' => 'EXTENDED',
                'limit' => $limit,
            ]);

        if ($response->failed()) {
            Log::channel('ebay-price-comparison')->error('eBay Browse search failed', [
                'keyword' => $keyword,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('eBay Browse search failed: ' . $response->body());
        }

        Log::channel('ebay-price-comparison')->debug('eBay Browse search response', [
            'keyword' => $keyword,
            'response' => $response->json(),
        ]);

        return $response->json('itemSummaries', []);
    }
}
