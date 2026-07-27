<?php

namespace App\Services\Ebay;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for eBay's Marketplace Insights API (buy/marketplace_insights).
 *
 * Requires eBay approval for the marketplace insights scope. Authenticates
 * with an application (client_credentials) token via EbayApiClient — this
 * queries public sold-item data, not any single seller's account, so it is
 * not tied to a SalesChannel.
 *
 * Usage:
 *   $client = app(EbayMarketplaceInsightsClient::class);
 *   $items = $client->searchSoldItems('Widget Pro 500', 30);
 */
class EbayMarketplaceInsightsClient
{
    public const BASE_URL = 'https://api.ebay.com/buy/marketplace_insights/v1_beta';

    private const DEFAULT_TIMEOUT = 60;
    private const DEFAULT_CONNECT_TIMEOUT = 30;
    private const DEFAULT_LIMIT = 50;

    public function __construct(protected EbayApiClient $apiClient)
    {
    }

    /**
     * Search sold items for a keyword within the last N days.
     *
     * @return array<int, array> raw itemSales entries from eBay
     */
    public function searchSoldItems(string $keyword, int $daysBack = 30, int $limit = self::DEFAULT_LIMIT): array
    {
        $token = $this->apiClient->getApplicationToken();

        $lastSoldFrom = gmdate('Y-m-d\TH:i:s.000\Z', strtotime("-{$daysBack} days"));
        $lastSoldTo = gmdate('Y-m-d\TH:i:s.000\Z');

        $response = Http::timeout(self::DEFAULT_TIMEOUT)
            ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
            ->withToken($token)
            ->withHeaders([
                'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US',
            ])
            ->get(self::BASE_URL . '/item_sales/search', [
                'q' => $keyword,
                'filter' => "lastSoldDate:[{$lastSoldFrom}..{$lastSoldTo}]",
                'limit' => $limit,
            ]);

        if ($response->failed()) {
            Log::channel('ebay-price-comparison')->error('eBay Marketplace Insights search failed', [
                'keyword' => $keyword,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('eBay Marketplace Insights search failed: ' . $response->body());
        }

        Log::channel('ebay-price-comparison')->debug('eBay Marketplace Insights search response', [
            'keyword' => $keyword,
            'response' => $response->json(),
        ]);

        return $response->json('itemSales', []);
    }
}
