<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Ebay\PriceComparisonService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CompareProductPriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    private const API_COOLDOWN_MICROSECONDS = 250000; // 250ms — throttle eBay API calls

    public function __construct(private int $productId)
    {
    }

    public function handle(PriceComparisonService $service): void
    {
        $product = Product::find($this->productId);
        if (!$product) {
            return;
        }

        try {
            $count = $service->compareProduct($product);
            Log::channel('ebay-price-comparison')->info('Compared', [
                'product_id' => $this->productId,
                'sellers_found' => $count,
            ]);
        } finally {
            usleep(self::API_COOLDOWN_MICROSECONDS);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('ebay-price-comparison')->error('Job failed', [
            'product_id' => $this->productId,
            'error' => $e->getMessage(),
        ]);
    }
}
