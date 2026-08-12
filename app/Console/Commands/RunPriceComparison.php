<?php

namespace App\Console\Commands;

use App\Jobs\CompareProductPriceJob;
use App\Models\Product;
use Illuminate\Console\Command;

class RunPriceComparison extends Command
{
    protected $signature = 'price-comparison:run {--limit=50} {--product=}';

    protected $description = 'Dispatch eBay competitor price comparison jobs for products (Market Research)';

    public function handle(): int
    {
        if ($productId = $this->option('product')) {
            CompareProductPriceJob::dispatch((int) $productId);
            $this->info("Dispatched comparison for product {$productId}");
            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');

        // NULL price_last_compared_at sorts first (never compared), then oldest-compared-first
        $products = Product::where('active_status', '1')
            ->where('delete_status', '0')
            ->orderByRaw('price_last_compared_at IS NOT NULL, price_last_compared_at ASC')
            ->limit($limit)
            ->get(['id', 'name']);

        foreach ($products as $product) {
            CompareProductPriceJob::dispatch($product->id);
        }

        $this->info("Dispatched {$products->count()} comparison jobs.");
        return self::SUCCESS;
    }
}
