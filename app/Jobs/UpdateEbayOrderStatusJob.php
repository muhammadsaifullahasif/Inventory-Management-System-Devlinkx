<?php

namespace App\Jobs;

use Exception;
use App\Models\Order;
use App\Models\SalesChannel;
use App\Services\Ebay\EbayApiClient;
use App\Services\Ebay\EbayService;
use App\Services\Ebay\EbayOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Job to update order statuses from eBay.
 *
 * This job fetches the latest status for orders that may have been
 * cancelled, refunded, or returned on eBay but not caught by webhooks.
 *
 * Runs every 12 hours via scheduler.
 */
class UpdateEbayOrderStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 1800; // 30 minutes

    /**
     * Maximum number of order IDs to send in a single API request.
     * eBay has limits on request size.
     */
    protected const BATCH_SIZE = 50;

    /**
     * Number of days back to check orders.
     * Orders older than this are likely already in final state.
     */
    protected int $daysBack;

    public function __construct(int $daysBack = 90)
    {
        $this->daysBack = $daysBack;
    }

    public function handle(
        EbayApiClient $client,
        EbayService $ebayService,
        EbayOrderService $orderService,
    ): void {
        Log::channel('ebay')->info('Starting eBay order status update job', [
            'days_back' => $this->daysBack,
        ]);

        $stats = [
            'total_checked' => 0,
            'updated' => 0,
            'cancelled' => 0,
            'refunded' => 0,
            'return_updated' => 0,
            'errors' => 0,
        ];

        try {
            // Get all active eBay sales channels
            // SalesChannel uses active_status column and isEbay() checks for client_id/client_secret
            $salesChannels = SalesChannel::where('active_status', 1)
                ->whereNotNull('client_id')
                ->whereNotNull('client_secret')
                ->where('client_id', '!=', '')
                ->where('client_secret', '!=', '')
                ->get();

            if ($salesChannels->isEmpty()) {
                Log::channel('ebay')->info('No active eBay sales channels found');
                return;
            }

            foreach ($salesChannels as $salesChannel) {
                try {
                    $channelStats = $this->processChannel($salesChannel, $client, $ebayService, $orderService);

                    // Aggregate stats
                    foreach ($channelStats as $key => $value) {
                        $stats[$key] += $value;
                    }
                } catch (Exception $e) {
                    Log::channel('ebay')->error('Failed to process sales channel', [
                        'sales_channel_id' => $salesChannel->id,
                        'sales_channel_name' => $salesChannel->name,
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors']++;
                }
            }

            Log::channel('ebay')->info('eBay order status update job completed', $stats);

        } catch (Exception $e) {
            Log::channel('ebay')->error('eBay order status update job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Process orders for a single sales channel.
     */
    protected function processChannel(
        SalesChannel $salesChannel,
        EbayApiClient $client,
        EbayService $ebayService,
        EbayOrderService $orderService,
    ): array {
        $stats = [
            'total_checked' => 0,
            'updated' => 0,
            'cancelled' => 0,
            'refunded' => 0,
            'return_updated' => 0,
            'errors' => 0,
        ];

        // Ensure we have a valid token
        $client->ensureValidToken($salesChannel);

        // Get orders that need status checking:
        // - Have eBay order ID
        // - Belong to this sales channel
        // - Created within the days_back period
        // - Not in final states (delivered, cancelled with cancel_complete, refunded)
        $cutoffDate = now()->subDays($this->daysBack);

        $orders = Order::where('sales_channel_id', $salesChannel->id)
            ->whereNotNull('ebay_order_id')
            ->where('ebay_order_id', '!=', '')
            ->where('created_at', '>=', $cutoffDate)
            ->where(function ($query) {
                // Exclude orders already in final states
                $query->whereNotIn('order_status', ['delivered', 'refunded'])
                    ->where(function ($q) {
                        // Include cancelled orders that might still have refund pending
                        $q->where('order_status', '!=', 'cancelled')
                            ->orWhere(function ($q2) {
                                $q2->where('order_status', 'cancelled')
                                    ->where('payment_status', 'paid');
                            });
                    });
            })
            ->pluck('ebay_order_id', 'id')
            ->toArray();

        if (empty($orders)) {
            Log::channel('ebay')->info('No orders to check for sales channel', [
                'sales_channel_id' => $salesChannel->id,
                'sales_channel_name' => $salesChannel->name,
            ]);
            return $stats;
        }

        $orderIds = array_values($orders);
        $localOrderIdMap = array_flip($orders); // ebay_order_id => local_id

        Log::channel('ebay')->info('Checking eBay order statuses', [
            'sales_channel_id' => $salesChannel->id,
            'order_count' => count($orderIds),
        ]);

        // Log to dedicated sync log
        Log::channel('ebay-order-sync')->info('=== Starting eBay order status sync ===', [
            'sales_channel_id' => $salesChannel->id,
            'sales_channel_name' => $salesChannel->name,
            'order_count' => count($orderIds),
            'order_ids' => $orderIds,
        ]);

        // Process orders in batches
        $batches = array_chunk($orderIds, self::BATCH_SIZE);
        $batchNumber = 0;

        foreach ($batches as $batchOrderIds) {
            $batchNumber++;
            Log::channel('ebay-order-sync')->info("Processing batch {$batchNumber}/" . count($batches), [
                'batch_order_ids' => $batchOrderIds,
            ]);

            try {
                $result = $ebayService->getOrdersByIds($salesChannel, $batchOrderIds);

                if (!$result['success']) {
                    Log::channel('ebay-order-sync')->warning('Failed to fetch order batch', [
                        'sales_channel_id' => $salesChannel->id,
                        'batch_order_ids' => $batchOrderIds,
                    ]);
                    $stats['errors']++;
                    continue;
                }

                Log::channel('ebay-order-sync')->info('Batch fetched successfully', [
                    'orders_returned' => count($result['orders']),
                ]);

                foreach ($result['orders'] as $ebayOrder) {
                    $stats['total_checked']++;

                    // Log that we're processing this order
                    Log::channel('ebay')->info('Processing eBay order', [
                        'ebay_order_id' => $ebayOrder['order_id'] ?? 'unknown',
                        'order_status' => $ebayOrder['order_status'] ?? null,
                        'cancel_status' => $ebayOrder['cancel_status'] ?? null,
                    ]);

                    // Save the full eBay order response to a separate JSON file
                    $this->saveOrderLog($ebayOrder, $salesChannel->id);

                    try {
                        $updateResult = $this->updateOrderStatus($ebayOrder, $salesChannel->id, $orderService);

                        if ($updateResult['updated']) {
                            $stats['updated']++;
                            if ($updateResult['cancelled']) {
                                $stats['cancelled']++;
                            }
                            if ($updateResult['refunded']) {
                                $stats['refunded']++;
                            }
                            if ($updateResult['return_updated']) {
                                $stats['return_updated']++;
                            }

                            // Log status change
                            Log::channel('ebay-order-sync')->info('Order status updated', [
                                'ebay_order_id' => $ebayOrder['order_id'] ?? 'unknown',
                                'cancelled' => $updateResult['cancelled'],
                                'refunded' => $updateResult['refunded'],
                                'return_updated' => $updateResult['return_updated'],
                            ]);
                        }
                    } catch (Exception $e) {
                        $stats['errors']++;
                        Log::channel('ebay-order-sync')->error('Failed to update order status', [
                            'ebay_order_id' => $ebayOrder['order_id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

            } catch (Exception $e) {
                $stats['errors']++;
                Log::channel('ebay-order-sync')->error('Failed to fetch order batch from eBay', [
                    'sales_channel_id' => $salesChannel->id,
                    'batch_order_ids' => $batchOrderIds,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Log completion summary
        Log::channel('ebay-order-sync')->info('=== eBay order status sync completed ===', [
            'sales_channel_id' => $salesChannel->id,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Update a single order's status based on eBay data.
     */
    protected function updateOrderStatus(array $ebayOrder, int $salesChannelId, EbayOrderService $orderService): array
    {
        $result = [
            'updated' => false,
            'cancelled' => false,
            'refunded' => false,
            'return_updated' => false,
        ];

        $localOrder = Order::where('ebay_order_id', $ebayOrder['order_id'])->first();

        if (!$localOrder) {
            return $result;
        }

        $updateData = [];
        $changes = [];

        // Map eBay status to local status
        $newOrderStatus = $orderService->mapOrderStatus($ebayOrder['order_status'], $ebayOrder);
        $newPaymentStatus = $orderService->mapPaymentStatus($ebayOrder['payment_status'], $ebayOrder);
        $newFulfillmentStatus = $orderService->mapFulfillmentStatus($ebayOrder);

        // Check for cancellation status changes
        // eBay returns PascalCase values like: CancelRequested, CancelComplete, CancelPending, NotApplicable, Invalid
        $cancelStatus = strtolower(trim($ebayOrder['cancel_status'] ?? ''));

        Log::channel('ebay')->info('Checking cancel status', [
            'ebay_order_id' => $ebayOrder['order_id'],
            'raw_cancel_status' => $ebayOrder['cancel_status'] ?? 'null',
            'normalized_cancel_status' => $cancelStatus,
            'local_order_status' => $localOrder->order_status,
        ]);

        // Skip if no cancellation or not applicable
        if (!empty($cancelStatus) && !in_array($cancelStatus, ['none', 'notapplicable', 'invalid', ''])) {
            // Cancelled/Complete states - order has been fully cancelled
            if (in_array($cancelStatus, ['cancelled', 'cancelcomplete', 'cancelclosed'])) {
                if ($localOrder->order_status !== 'cancelled') {
                    $updateData['order_status'] = 'cancelled';
                    $updateData['cancel_status'] = $ebayOrder['cancel_status'];
                    $changes[] = "order_status changed to cancelled (eBay: {$ebayOrder['cancel_status']})";
                    $result['cancelled'] = true;

                    // Restore inventory for newly cancelled orders
                    foreach ($localOrder->items as $item) {
                        if ($item->inventory_updated) {
                            $item->restoreInventory();
                        }
                    }
                }
            }
            // Pending/Requested states - cancellation in progress
            elseif (in_array($cancelStatus, ['cancelrequested', 'cancelpending'])) {
                if ($localOrder->order_status !== 'cancellation_requested' && $localOrder->order_status !== 'cancelled') {
                    $updateData['order_status'] = 'cancellation_requested';
                    $updateData['cancel_status'] = $ebayOrder['cancel_status'];
                    $changes[] = "order_status changed to cancellation_requested (eBay: {$ebayOrder['cancel_status']})";
                }
            }
            // Rejected state - cancellation was denied
            elseif (in_array($cancelStatus, ['cancelrejected', 'cancelclosedwithrefund', 'cancelclosednorefund', 'cancelfailed'])) {
                // Just update the cancel_status field for tracking, don't change order_status
                if ($localOrder->cancel_status !== $ebayOrder['cancel_status']) {
                    $updateData['cancel_status'] = $ebayOrder['cancel_status'];
                    $changes[] = "cancel_status updated to {$ebayOrder['cancel_status']}";
                }
            }
        }

        // Check for payment status changes (refunds)
        if ($newPaymentStatus === 'refunded' && $localOrder->payment_status !== 'refunded') {
            $updateData['payment_status'] = 'refunded';
            $updateData['order_status'] = 'refunded';
            $updateData['refund_status'] = 'completed';
            $updateData['refund_completed_at'] = now();
            $changes[] = 'payment_status changed to refunded';
            $result['refunded'] = true;

            // Restore inventory for refunded orders
            foreach ($localOrder->items as $item) {
                if ($item->inventory_updated) {
                    $item->restoreInventory();
                }
            }
        }

        // Check for fulfillment status changes (shipped)
        if (!empty($ebayOrder['shipped_time']) && empty($localOrder->shipped_at)) {
            $updateData['shipped_at'] = new \DateTime($ebayOrder['shipped_time']);
            $updateData['order_status'] = $newOrderStatus === 'cancelled' ? $localOrder->order_status : 'shipped';
            $updateData['fulfillment_status'] = 'fulfilled';
            $changes[] = 'shipped_at updated';
        }

        // Update tracking info if available
        if (!empty($ebayOrder['tracking_number']) && empty($localOrder->tracking_number)) {
            $updateData['tracking_number'] = $ebayOrder['tracking_number'];
            $changes[] = 'tracking_number updated';
        }
        if (!empty($ebayOrder['shipping_carrier']) && empty($localOrder->shipping_carrier)) {
            $updateData['shipping_carrier'] = $ebayOrder['shipping_carrier'];
            $changes[] = 'shipping_carrier updated';
        }

        // Update eBay-specific status fields
        if (strtolower($localOrder->ebay_order_status) !== strtolower($ebayOrder['order_status'])) {
            $updateData['ebay_order_status'] = $ebayOrder['order_status'];
        }
        if (strtolower($localOrder->ebay_payment_status) !== strtolower($ebayOrder['payment_status'])) {
            $updateData['ebay_payment_status'] = $ebayOrder['payment_status'];
        }

        if (in_array($ebayOrder['order_status'], ['Cancelled']) || in_array($ebayOrder['cancel_status'], ['CancelComplete', 'CancelClosed', 'CancelClosedWithRefund', 'CancelClosedNoRefund'])) {
            if (strtolower($localOrder->order_status) !== 'cancelled') {
                $localOrder->update(['order_status' => 'cancelled']);
            }
        }

        // Only update if there are changes
        if (!empty($updateData)) {
            $localOrder->update($updateData);
            $result['updated'] = true;

            // Log the status change
            $localOrder->setMeta('status_sync_' . time(), [
                'source' => 'UpdateEbayOrderStatusJob',
                'changes' => $changes,
                'ebay_order_status' => $ebayOrder['order_status'],
                'ebay_payment_status' => $ebayOrder['payment_status'],
                'ebay_cancel_status' => $ebayOrder['cancel_status'] ?? '',
                'timestamp' => now()->toIso8601String(),
            ]);

            Log::channel('ebay')->info('Order status updated from eBay', [
                'order_id' => $localOrder->id,
                'ebay_order_id' => $ebayOrder['order_id'],
                'changes' => $changes,
            ]);
        }

        return $result;
    }

    public function failed(Exception $exception): void
    {
        Log::channel('ebay')->error('UpdateEbayOrderStatusJob failed completely', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Save each order's eBay response to a separate JSON file.
     * Files are saved in storage/logs/ebay/orders/{date}/{order_id}.json
     */
    protected function saveOrderLog(array $ebayOrder, int $salesChannelId): void
    {
        $orderId = $ebayOrder['order_id'] ?? 'unknown';

        try {
            // Sanitize order ID for filename (replace special chars)
            $safeOrderId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $orderId);

            $date = now()->format('Y-m-d');
            $directory = storage_path("logs/ebay/orders/{$date}");

            // Create directory if it doesn't exist
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            $logData = [
                'fetched_at' => now()->toIso8601String(),
                'sales_channel_id' => $salesChannelId,
                'ebay_order_id' => $orderId,
                'order_status' => $ebayOrder['order_status'] ?? null,
                'payment_status' => $ebayOrder['payment_status'] ?? null,
                'checkout_status' => $ebayOrder['checkout_status'] ?? null,
                'cancel_status' => $ebayOrder['cancel_status'] ?? null,
                'shipped_time' => $ebayOrder['shipped_time'] ?? null,
                'paid_time' => $ebayOrder['paid_time'] ?? null,
                'created_time' => $ebayOrder['created_time'] ?? null,
                'tracking_number' => $ebayOrder['tracking_number'] ?? null,
                'shipping_carrier' => $ebayOrder['shipping_carrier'] ?? null,
                'buyer' => $ebayOrder['buyer'] ?? null,
                'total' => $ebayOrder['total'] ?? null,
                'subtotal' => $ebayOrder['subtotal'] ?? null,
                'shipping_cost' => $ebayOrder['shipping_cost'] ?? null,
                'currency' => $ebayOrder['currency'] ?? null,
                'line_items' => $ebayOrder['line_items'] ?? null,
                'shipping_address' => $ebayOrder['shipping_address'] ?? null,
                'raw_data' => $ebayOrder['raw_data'] ?? $ebayOrder,
            ];

            $filePath = "{$directory}/{$safeOrderId}.json";
            $jsonContent = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            // Write the file
            $result = file_put_contents($filePath, $jsonContent);

            if ($result === false) {
                Log::channel('ebay')->error('Failed to write order log file', [
                    'ebay_order_id' => $orderId,
                    'file_path' => $filePath,
                    'directory_exists' => File::isDirectory($directory),
                    'directory_writable' => is_writable($directory),
                ]);
            } else {
                Log::channel('ebay')->info('Order response saved to file', [
                    'ebay_order_id' => $orderId,
                    'file_path' => $filePath,
                    'bytes_written' => $result,
                ]);
            }
        } catch (Exception $e) {
            Log::channel('ebay')->error('Exception while saving order log file', [
                'ebay_order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
