<?php

namespace App\Services\Ebay;

use App\Models\Product;
use App\Models\ProductPriceComparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finds competing eBay sellers for a product, ranks them by sales volume
 * in the last month, and stores the top sellers' prices.
 */
class PriceComparisonService
{
    private const TOP_SELLERS_COUNT = 4;
    private const DAYS_BACK = 30;

    public function __construct(protected EbayMarketplaceInsightsClient $insightsClient)
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

        $itemSales = $this->insightsClient->searchSoldItems($keyword, self::DAYS_BACK);

        $topSellers = $this->rankSellersBySalesVolume($itemSales);

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
     * Build an eBay search keyword for the product. Prefers barcode/SKU
     * (more precise match) and falls back to the product name.
     */
    protected function buildSearchKeyword(Product $product): string
    {
        return $product->barcode ?: $product->name ?: '';
    }

    /**
     * Group raw itemSales entries by seller, sum sold quantity, and return
     * the top N sellers sorted by sales volume with their most recent price.
     *
     * @return array<int, array{seller: string, price: float, currency: string, sold: int, item_id: ?string, url: ?string}>
     */
    protected function rankSellersBySalesVolume(array $itemSales): array
    {
        $bySeller = [];

        foreach ($itemSales as $sale) {
            $seller = $sale['seller']['username'] ?? null;
            if (!$seller) {
                continue;
            }

            $price = (float) ($sale['lastSoldPrice']['value'] ?? 0);
            $currency = $sale['lastSoldPrice']['currency'] ?? 'USD';
            $soldQuantity = (int) ($sale['totalSoldQuantity'] ?? 1);
            $soldDate = $sale['lastSoldDate'] ?? null;

            if (!isset($bySeller[$seller])) {
                $bySeller[$seller] = [
                    'seller' => $seller,
                    'price' => $price,
                    'currency' => $currency,
                    'sold' => 0,
                    'item_id' => $sale['itemId'] ?? null,
                    'url' => $sale['itemWebUrl'] ?? null,
                    'latest_sold_date' => $soldDate,
                ];
            }

            $bySeller[$seller]['sold'] += $soldQuantity;

            // Keep price/url from the most recently sold entry for this seller.
            if ($soldDate && $soldDate > $bySeller[$seller]['latest_sold_date']) {
                $bySeller[$seller]['price'] = $price;
                $bySeller[$seller]['currency'] = $currency;
                $bySeller[$seller]['item_id'] = $sale['itemId'] ?? null;
                $bySeller[$seller]['url'] = $sale['itemWebUrl'] ?? null;
                $bySeller[$seller]['latest_sold_date'] = $soldDate;
            }
        }

        $sellers = array_values($bySeller);

        usort($sellers, fn ($a, $b) => $b['sold'] <=> $a['sold']);

        return array_slice($sellers, 0, self::TOP_SELLERS_COUNT);
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
