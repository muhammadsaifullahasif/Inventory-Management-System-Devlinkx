<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Jobs\CompareProductPriceJob;
use Illuminate\Console\Command;

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
                return 1;
            }

            CompareProductPriceJob::dispatch($product->id);
            $this->info("Dispatched price comparison for product {$product->id} ({$product->name}).");
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
            return 0;
        }

        foreach ($products as $product) {
            CompareProductPriceJob::dispatch($product->id);
        }

        $this->info("Dispatched price comparison for {$products->count()} product(s).");

        return 0;
    }
}
