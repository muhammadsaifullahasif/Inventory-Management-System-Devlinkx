<?php

namespace App\Services\Ebay;

use App\Models\Product;
use App\Models\ProductPriceComparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finds competing eBay sellers for a product among active listings, ranks
 * them by estimated units sold, and stores the top sellers' prices.
 */
class PriceComparisonService
{
    private const TOP_SELLERS_COUNT = 4;
    private const SEARCH_LIMIT = 50;

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

        $imageBase64 = $this->fetchProductImageBase64($product);

        $itemSummaries = $imageBase64
            ? $this->browseClient->searchByImage($imageBase64, $keyword, self::SEARCH_LIMIT)
            : $this->browseClient->searchActiveListings($keyword, self::SEARCH_LIMIT);

        $topSellers = $this->rankSellersBySales($itemSummaries);

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
     * Fetch the product's image and base64-encode it for eBay's
     * search_by_image call. Returns null (falls back to text-only search)
     * if the product has no image or it can't be read.
     */
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
            Log::channel('ebay-price-comparison')->warning('Failed to read product image for image search', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Group raw itemSummaries entries by seller, keep each seller's
     * best-selling listing (by estimatedSoldQuantity), and return the top N
     * sellers sorted by units sold, highest first.
     *
     * @return array<int, array{seller: string, price: float, currency: string, sold: int, item_id: ?string, url: ?string}>
     */
    protected function rankSellersBySales(array $itemSummaries): array
    {
        $bySeller = [];

        foreach ($itemSummaries as $item) {
            $seller = $item['seller']['username'] ?? null;
            if (!$seller) {
                continue;
            }

            $sold = (int) ($item['estimatedAvailabilities'][0]['estimatedSoldQuantity'] ?? 0);
            $price = (float) ($item['price']['value'] ?? 0);
            $currency = $item['price']['currency'] ?? 'USD';

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
