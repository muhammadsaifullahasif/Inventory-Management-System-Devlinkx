<?php

namespace App\Services\Ebay;

use App\Models\Product;
use App\Models\ProductPriceComparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finds competing eBay sellers for a product among active listings, ranks
 * them by estimated units sold, and stores the top sellers' prices.
 */
class PriceComparisonService
{
    private const TOP_SELLERS_COUNT = 4;
    private const SEARCH_LIMIT = 50;
    private const DETAIL_CANDIDATE_LIMIT = 15;

    public function __construct(protected EbayBrowseClient $browseClient)
    {
    }

    /**
     * Compare a product's price against top competing sellers and persist
     * the result. Returns the number of competitor rows stored.
     */
    public function compareProduct(Product $product): int
    {
        $keyword = $this->buildSearchKeyword($product);

        if (empty($keyword)) {
            Log::channel('ebay-price-comparison')->warning('Skipping product with no usable search keyword', [
                'product_id' => $product->id,
            ]);
            return 0;
        }

        $itemSummaries = $this->browseClient->searchActiveListings($keyword, self::SEARCH_LIMIT);

        $candidates = $this->pickCandidateSellers($itemSummaries);

        $topSellers = $this->rankSellersBySales($candidates);

        if (empty($topSellers)) {
            Log::channel('ebay-price-comparison')->info('No competing sellers found', [
                'product_id' => $product->id,
                'keyword' => $keyword,
            ]);
            return 0;
        }

        $this->storeComparisons($product, $topSellers);

        $product->forceFill(['price_last_compared_at' => now()])->save();

        return count($topSellers);
    }

    /**
     * Build an eBay search keyword for the product. Uses the product title —
     * barcode/SKU values are internal identifiers, not listed on eBay, and
     * return zero results.
     */
    protected function buildSearchKeyword(Product $product): string
    {
        return $product->name ?: '';
    }

    /**
     * Deduplicate search results down to one (best-relevance) listing per
     * seller, capped at DETAIL_CANDIDATE_LIMIT — the pool that gets a
     * per-item detail lookup for real sold-quantity data.
     *
     * @return array<int, array{seller: string, price: float, currency: string, item_id: ?string, url: ?string}>
     */
    protected function pickCandidateSellers(array $itemSummaries): array
    {
        $bySeller = [];

        foreach ($itemSummaries as $item) {
            $seller = $item['seller']['username'] ?? null;
            if (!$seller || isset($bySeller[$seller])) {
                continue;
            }

            $bySeller[$seller] = [
                'seller' => $seller,
                'price' => (float) ($item['price']['value'] ?? 0),
                'currency' => $item['price']['currency'] ?? 'USD',
                'item_id' => $item['itemId'] ?? null,
                'url' => $item['itemWebUrl'] ?? null,
            ];

            if (count($bySeller) >= self::DETAIL_CANDIDATE_LIMIT) {
                break;
            }
        }

        return array_values($bySeller);
    }

    /**
     * Look up sold-quantity for each candidate via the item detail endpoint
     * (not available on search results), and return the top N sellers
     * sorted by units sold, highest first.
     *
     * @return array<int, array{seller: string, price: float, currency: string, sold: int, item_id: ?string, url: ?string}>
     */
    protected function rankSellersBySales(array $candidates): array
    {
        foreach ($candidates as &$candidate) {
            $candidate['sold'] = $this->fetchSoldQuantity($candidate['item_id']);
        }
        unset($candidate);

        usort($candidates, fn ($a, $b) => $b['sold'] <=> $a['sold']);

        return array_slice($candidates, 0, self::TOP_SELLERS_COUNT);
    }

    /**
     * Fetch estimatedSoldQuantity for a single item. Detail lookups can fail
     * per-item (rate limit, delisted item); treat as 0 sold rather than
     * aborting the whole comparison.
     */
    protected function fetchSoldQuantity(?string $itemId): int
    {
        if (!$itemId) {
            return 0;
        }

        try {
            $detail = $this->browseClient->getItemDetail($itemId);
        } catch (\Throwable $e) {
            Log::channel('ebay-price-comparison')->warning('Item detail lookup failed, treating as 0 sold', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }

        return (int) ($detail['estimatedAvailabilities'][0]['estimatedSoldQuantity'] ?? 0);
    }

    /**
     * Replace stored comparisons for the product with the given top sellers.
     */
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
                    'rank' => $index + 1,
                    'captured_at' => $now,
                ]);
            }
        });
    }
}
