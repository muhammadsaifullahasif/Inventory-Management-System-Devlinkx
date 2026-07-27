<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\SalesChannelProduct;
use App\Models\InventorySyncLog;
use App\Services\Inventory\InventorySyncService;
use App\Services\Inventory\VisibleStockCalculator;
use App\Jobs\SyncInventoryToEbayJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for inventory sync operations.
 *
 * Endpoints:
 * - GET  /inventory-sync/status/{product}     - Get sync status for product
 * - POST /inventory-sync/sync/{product}       - Trigger sync for product
 * - POST /inventory-sync/queue/{product}      - Queue async sync
 * - GET  /inventory-sync/logs                 - Get sync logs
 * - POST /inventory-sync/settings/{listing}   - Update listing sync settings
 */
class InventorySyncController extends Controller
{
    public function __construct(
        private InventorySyncService $syncService,
        private VisibleStockCalculator $calculator
    ) {}

    /**
     * Get sync status for a product.
     *
     * GET /inventory-sync/status/{product}
     */
    public function status(Product $product): JsonResponse
    {
        $status = $this->syncService->getSyncStatus($product);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Preview what would be synced (dry run).
     *
     * GET /inventory-sync/preview/{product}
     */
    public function preview(Product $product): JsonResponse
    {
        $calculations = $this->calculator->calculateForAllStores($product);
        $listingsNeedingSync = $this->calculator->getListingsNeedingSync($product);

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'central_stock' => $product->available_stock,
                'calculations' => collect($calculations)->map(fn ($calc) => $calc->toArray())->toArray(),
                'needs_sync_count' => count($listingsNeedingSync),
                'listings_needing_sync' => collect($listingsNeedingSync)->map(fn ($item) => [
                    'sales_channel_id' => $item['listing']->sales_channel_id,
                    'decision' => $item['decision']->toArray(),
                    'result' => $item['result']->toArray(),
                ])->values()->toArray(),
            ],
        ]);
    }

    /**
     * Trigger immediate sync for a product.
     *
     * POST /inventory-sync/sync/{product}
     */
    public function sync(Request $request, Product $product): JsonResponse
    {
        $channelId = $request->input('channel_id');

        if ($channelId) {
            $channel = SalesChannel::findOrFail($channelId);
            $results = [$this->syncService->syncToStore($product, $channel, 'manual')];
        } else {
            $results = $this->syncService->syncChangedListings($product, 'manual');
        }

        $summary = [
            'synced' => collect($results)->filter(fn ($r) => $r->wasSynced())->count(),
            'skipped' => collect($results)->filter(fn ($r) => $r->wasSkipped())->count(),
            'failed' => collect($results)->filter(fn ($r) => $r->hasFailed())->count(),
        ];

        return response()->json([
            'success' => $summary['failed'] === 0,
            'data' => [
                'summary' => $summary,
                'results' => collect($results)->map(fn ($r) => $r->toArray())->toArray(),
            ],
        ]);
    }

    /**
     * Queue async sync for a product.
     *
     * POST /inventory-sync/queue/{product}
     */
    public function queue(Product $product): JsonResponse
    {
        $queued = $this->syncService->queueSync($product, 'manual');

        return response()->json([
            'success' => true,
            'data' => [
                'jobs_queued' => $queued,
                'message' => $queued > 0
                    ? "Queued {$queued} sync jobs"
                    : 'No sync needed',
            ],
        ]);
    }

    /**
     * Force sync all stores (ignores change detection).
     *
     * POST /inventory-sync/force/{product}
     */
    public function forceSync(Product $product): JsonResponse
    {
        $results = $this->syncService->syncToAllStores($product, 'manual_force');

        $summary = [
            'synced' => collect($results)->filter(fn ($r) => $r->wasSynced())->count(),
            'skipped' => collect($results)->filter(fn ($r) => $r->wasSkipped())->count(),
            'failed' => collect($results)->filter(fn ($r) => $r->hasFailed())->count(),
        ];

        return response()->json([
            'success' => $summary['failed'] === 0,
            'data' => [
                'summary' => $summary,
                'results' => collect($results)->map(fn ($r) => $r->toArray())->toArray(),
            ],
        ]);
    }

    /**
     * Get sync logs.
     *
     * GET /inventory-sync/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $query = InventorySyncLog::with(['product:id,sku,name', 'salesChannel:id,name'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($channelId = $request->input('channel_id')) {
            $query->where('sales_channel_id', $channelId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($hours = $request->input('hours')) {
            $query->where('created_at', '>=', now()->subHours((int) $hours));
        }

        $logs = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Update sync settings for a listing.
     *
     * POST /inventory-sync/settings/{listing}
     */
    public function updateSettings(Request $request, int $productId, int $channelId): JsonResponse
    {
        $listing = SalesChannelProduct::where('product_id', $productId)
            ->where('sales_channel_id', $channelId)
            ->firstOrFail();

        $validated = $request->validate([
            'visible_quantity' => 'sometimes|integer|min:1|max:999',
            'sync_enabled' => 'sometimes|boolean',
        ]);

        $listing->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $listing->product_id,
                'sales_channel_id' => $listing->sales_channel_id,
                'visible_quantity' => $listing->visible_quantity,
                'sync_enabled' => $listing->sync_enabled,
            ],
        ]);
    }

    /**
     * Sync all products for a sales channel.
     *
     * POST /inventory-sync/channel/{channel}
     */
    public function syncChannel(SalesChannel $channel): JsonResponse
    {
        // Get all active products for this channel
        $productIds = SalesChannelProduct::where('sales_channel_id', $channel->id)
            ->where('listing_status', SalesChannelProduct::STATUS_ACTIVE)
            ->where('sync_enabled', true)
            ->pluck('product_id')
            ->unique();

        if ($productIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'jobs_queued' => 0,
                    'message' => 'No active products found for this channel',
                ],
            ]);
        }

        $totalQueued = 0;

        // Queue sync for each product
        foreach ($productIds as $productId) {
            $product = Product::find($productId);
            if (!$product) {
                continue;
            }

            // Check if this specific listing needs sync
            $listing = SalesChannelProduct::where('product_id', $productId)
                ->where('sales_channel_id', $channel->id)
                ->first();

            if (!$listing) {
                continue;
            }

            $decision = $this->calculator->shouldSync($product, $listing);

            if ($decision->shouldSync) {
                SyncInventoryToEbayJob::dispatch(
                    $product->id,
                    $channel->id,
                    'manual_channel_sync',
                    "channel:{$channel->id}"
                )->onQueue('inventory-sync');

                $totalQueued++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'jobs_queued' => $totalQueued,
                'total_products' => $productIds->count(),
                'message' => $totalQueued > 0
                    ? "Queued {$totalQueued} inventory sync jobs for {$channel->name}"
                    : 'No products need syncing',
            ],
        ]);
    }

    /**
     * Get sync statistics.
     *
     * GET /inventory-sync/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $hours = (int) $request->input('hours', 24);

        $stats = [
            'period_hours' => $hours,
            'total_syncs' => InventorySyncLog::recent($hours)->count(),
            'successful' => InventorySyncLog::recent($hours)->successful()->count(),
            'failed' => InventorySyncLog::recent($hours)->failed()->count(),
            'skipped' => InventorySyncLog::recent($hours)->where('status', 'skipped')->count(),
            'products_synced' => InventorySyncLog::recent($hours)
                ->successful()
                ->distinct('product_id')
                ->count('product_id'),
            'channels_synced' => InventorySyncLog::recent($hours)
                ->successful()
                ->distinct('sales_channel_id')
                ->count('sales_channel_id'),
        ];

        // Recent failures
        $stats['recent_failures'] = InventorySyncLog::recent($hours)
            ->failed()
            ->with(['product:id,sku', 'salesChannel:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'product_sku' => $log->product?->sku,
                'channel' => $log->salesChannel?->name,
                'error' => $log->error_message,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
