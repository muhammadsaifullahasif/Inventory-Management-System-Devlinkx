<?php

namespace App\Services\Ebay;

use App\Models\Product;
use App\Models\ProductPriceComparison;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceComparisonService
{
    private const TOP_SELLERS_COUNT = 4;
    private const SEARCH_LIMIT = 50;

    public function __construct(private EbayBrowseClient $browseClient)
    {
    }

    public function compareProduct(Product $product): int
    {
        $keyword = $this->buildSearchKeyword($product);
        if (empty($keyword)) {
            Log::channel('ebay-price-comparison')->info('Skipped, no keyword', ['product_id' => $product->id]);
            return 0;
        }

        $imageBase64 = $this->fetchProductImageBase64($product);

        $itemSummaries = $imageBase64
            ? $this->browseClient->searchByImage($imageBase64, $keyword, self::SEARCH_LIMIT)
            : $this->browseClient->searchActiveListings($keyword, self::SEARCH_LIMIT);

        $topSellers = $this->rankSellersBySales($itemSummaries);
        if (empty($topSellers)) {
            Log::channel('ebay-price-comparison')->info('No sellers found', ['product_id' => $product->id]);
            return 0;
        }

        $this->storeComparisons($product, $topSellers);
        $product->forceFill(['price_last_compared_at' => now()])->save();

        return count($topSellers);
    }

    private const MAX_TAG_TERMS = 5; // cap tags appended to query — too many terms over-narrows eBay's match

    protected function buildSearchKeyword(Product $product): string
    {
        // SKU/barcode are internal-only identifiers, not listed on eBay — searching them returns zero results.
        $name = trim($product->name ?? '');
        if (empty($name)) {
            return '';
        }

        $tags = $this->extractTags($product);
        if (empty($tags)) {
            return $name;
        }

        return trim($name . ' ' . implode(' ', $tags));
    }

    /**
     * Product tags (stored as a comma-separated meta value) used to widen/sharpen
     * the eBay search query alongside the product name.
     */
    protected function extractTags(Product $product): array
    {
        $raw = $product->product_meta['tags'] ?? null;
        if (empty($raw)) {
            return [];
        }

        $tags = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return array_slice($tags, 0, self::MAX_TAG_TERMS);
    }

    protected function fetchProductImageBase64(Product $product): ?string
    {
        $url = $product->getImageUrl();
        if (!$url) {
            return null;
        }

        try {
            if (filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, url('/'))) {
                $response = Http::timeout(30)->get($url);
                if ($response->failed()) {
                    return null;
                }
                return base64_encode($response->body());
            }

            $relativePath = str_starts_with($product->product_image, 'products/')
                ? storage_path('app/public/' . $product->product_image)
                : public_path('uploads/' . $product->product_image);

            if (!is_file($relativePath)) {
                return null;
            }

            return base64_encode(file_get_contents($relativePath));
        } catch (\Throwable $e) {
            Log::channel('ebay-price-comparison')->warning('Image fetch failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Our own eBay seller usernames (from connected sales channels), lowercased.
     * Excluded from competitor rankings — we're comparing against others, not ourselves.
     */
    protected function ourSellerUsernames(): array
    {
        static $usernames = null;

        if ($usernames === null) {
            $usernames = SalesChannel::query()
                ->whereNotNull('ebay_user_ids')
                ->orWhereNotNull('ebay_user_id')
                ->get(['ebay_user_id', 'ebay_user_ids'])
                ->flatMap(fn ($channel) => array_merge(
                    $channel->ebay_user_id ? [$channel->ebay_user_id] : [],
                    $channel->ebay_user_ids ?? []
                ))
                ->filter()
                ->map(fn ($username) => strtolower($username))
                ->unique()
                ->values()
                ->all();
        }

        return $usernames;
    }

    protected function rankSellersBySales(array $itemSummaries): array
    {
        $bySeller = [];
        $ourUsernames = $this->ourSellerUsernames();

        foreach ($itemSummaries as $item) {
            $seller = $item['seller']['username'] ?? null;
            if (!$seller) {
                continue;
            }

            // Skip our own listings — this is a competitor comparison, not a self-listing
            if (in_array(strtolower($seller), $ourUsernames, true)) {
                continue;
            }

            $sold = (int) ($item['estimatedAvailabilities'][0]['estimatedSoldQuantity'] ?? 0);
            $price = (float) ($item['price']['value'] ?? 0);
            $currency = $item['price']['currency'] ?? 'USD';

            // dedupe by seller, keep their best-selling listing among the results
            if (!isset($bySeller[$seller]) || $sold > $bySeller[$seller]['sold']) {
                $bySeller[$seller] = [
                    'seller' => $seller,
                    'price' => $price,
                    'currency' => $currency,
                    'sold' => $sold,
                    'item_id' => $item['itemId'] ?? null,
                    'url' => $item['itemWebUrl'] ?? null,
                ];
            }
        }

        $sellers = array_values($bySeller);
        usort($sellers, fn ($a, $b) => $b['sold'] <=> $a['sold']); // rank by units sold, not price

        return array_slice($sellers, 0, self::TOP_SELLERS_COUNT);
    }

    protected function storeComparisons(Product $product, array $topSellers): void
    {
        $now = now();

        DB::transaction(function () use ($product, $topSellers, $now) {
            ProductPriceComparison::where('product_id', $product->id)->delete();

            foreach ($topSellers as $index => $seller) {
                ProductPriceComparison::create([
                    'product_id' => $product->id,
                    'competitor_seller' => $seller['seller'],
                    'competitor_price' => $seller['price'],
                    'currency' => $seller['currency'],
                    'items_sold_last_month' => $seller['sold'],
                    'ebay_item_id' => $seller['item_id'],
                    'listing_url' => $seller['url'],
                    'rank' => $index + 1, // 1 = most units sold
                    'captured_at' => $now,
                ]);
            }
        });
    }
}
