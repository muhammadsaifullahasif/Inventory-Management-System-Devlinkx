<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Jobs\CompareProductPriceJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunPriceComparison extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price-comparison:run
                            {--limit=50 : Number of products to compare in this run}
                            {--product= : Compare a single product by ID (ignores --limit rotation)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch price comparison jobs for products, rotating oldest-compared first';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($productId = $this->option('product')) {
            $product = Product::find($productId);

            if (!$product) {
                $this->error("Product {$productId} not found.");
                Log::channel('ebay-price-comparison')->error('price-comparison:run: product not found', [
                    'product_id' => $productId,
                ]);
                return 1;
            }

            CompareProductPriceJob::dispatch($product->id);
            $this->info("Dispatched price comparison for product {$product->id} ({$product->name}).");
            Log::channel('ebay-price-comparison')->info('price-comparison:run dispatched', [
                'product_id' => $product->id,
                'product_name' => $product->name,
            ]);
            return 0;
        }

        $limit = max(1, (int) $this->option('limit'));

        $products = Product::where('active_status', '1')
            ->where('delete_status', '0')
            ->orderByRaw('price_last_compared_at IS NOT NULL, price_last_compared_at ASC')
            ->limit($limit)
            ->get(['id', 'name']);

        if ($products->isEmpty()) {
            $this->warn('No active products found to compare.');
            Log::channel('ebay-price-comparison')->warning('price-comparison:run: no active products found');
            return 0;
        }

        foreach ($products as $product) {
            CompareProductPriceJob::dispatch($product->id);
        }

        $this->info("Dispatched price comparison for {$products->count()} product(s).");
        Log::channel('ebay-price-comparison')->info('price-comparison:run dispatched batch', [
            'count' => $products->count(),
            'product_ids' => $products->pluck('id')->all(),
        ]);

        return 0;
    }
}
