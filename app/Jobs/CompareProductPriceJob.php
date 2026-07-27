<?php

namespace App\Jobs;

use Exception;
use App\Models\Product;
use App\Services\Ebay\PriceComparisonService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CompareProductPriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    private const API_COOLDOWN_MICROSECONDS = 250000;

    protected int $productId;

    public function __construct(int $productId)
    {
        $this->productId = $productId;
    }

    public function handle(PriceComparisonService $priceComparisonService): void
    {
        try {
            $product = Product::findOrFail($this->productId);

            $storedCount = $priceComparisonService->compareProduct($product);

            Log::channel('ebay-price-comparison')->info('Price comparison complete', [
                'product_id' => $this->productId,
                'competitors_stored' => $storedCount,
            ]);

            usleep(self::API_COOLDOWN_MICROSECONDS);
        } catch (Exception $e) {
            Log::channel('ebay-price-comparison')->error('Price comparison job failed', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::channel('ebay-price-comparison')->error('Price comparison job failed completely', [
            'product_id' => $this->productId,
            'error' => $exception->getMessage(),
        ]);
    }
}
