<?php

namespace App\Services\Ebay;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wraps eBay's public Buy Browse API (item_summary/search, search_by_image).
 * Uses the app-level (client_credentials) token — no per-seller authorization needed.
 */
class EbayBrowseClient
{
    private const BASE_URL = 'https://api.ebay.com/buy/browse/v1';

    public function __construct(private EbayApiClient $apiClient)
    {
    }

    public function searchActiveListings(string $keyword, int $limit = 50): array
    {
        $token = $this->apiClient->getApplicationToken();

        $response = Http::timeout(60)->connectTimeout(30)
            ->withToken($token)
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'])
            ->get(self::BASE_URL . '/item_summary/search', [
                'q' => $keyword,
                'filter' => 'buyingOptions:{FIXED_PRICE}, itemLocationCountry:US',
                'fieldgroups' => 'EXTENDED', // required to get estimatedAvailabilities.estimatedSoldQuantity
                'limit' => $limit,
            ]);

        if ($response->failed()) {
            Log::channel('ebay-price-comparison')->warning('eBay search failed', ['body' => $response->body()]);
            return [];
        }

        return $response->json('itemSummaries', []);
    }

    public function searchByImage(string $base64Image, ?string $keyword, int $limit = 50): array
    {
        $token = $this->apiClient->getApplicationToken();

        $query = [
            'filter' => 'buyingOptions:{FIXED_PRICE}, itemLocationCountry:US',
            'limit' => $limit,
        ];
        if ($keyword) {
            $query['q'] = $keyword;
        }

        // eBay requires query params in the URL even on this POST endpoint — only `image` goes in the body.
        $response = Http::timeout(60)->connectTimeout(30)
            ->withToken($token)
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'])
            ->post(self::BASE_URL . '/item_summary/search_by_image?' . http_build_query($query), [
                'image' => $base64Image,
            ]);

        if ($response->failed()) {
            Log::channel('ebay-price-comparison')->warning('eBay image search failed', ['body' => $response->body()]);
            return [];
        }

        return $response->json('itemSummaries', []);
    }
}
