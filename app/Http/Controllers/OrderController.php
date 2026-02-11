<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\OrderMeta;
use App\Models\SalesChannel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display a listing of orders
     */
    public function index(Request $request)
    {
        $query = Order::with(['items', 'salesChannel']);

        // Filter by sales channel
        if ($request->filled('sales_channel_id')) {
            $query->where('sales_channel_id', $request->sales_channel_id);
        }

        // Filter by order status
        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by fulfillment status
        if ($request->filled('fulfillment_status')) {
            $query->where('fulfillment_status', $request->fulfillment_status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }

        // Search by order number, buyer email, or eBay order ID
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('ebay_order_id', 'like', "%{$search}%")
                    ->orWhere('buyer_email', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->input('per_page', 20);
        $orders = $query->paginate($perPage);

        // Get sales channels for filter dropdown
        $salesChannels = SalesChannel::all();

        // Return JSON if requested via API
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);
        }

        return view('orders.index', compact('orders', 'salesChannels'));
    }

    /**
     * Show the form for creating a new order (for web views)
     */
    public function create()
    {
        $salesChannels = SalesChannel::all();

        return view('orders.create', compact('salesChannels'));
    }

    /**
     * Store a newly created order
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sales_channel_id' => 'required|exists:sales_channels,id',
            'buyer_email' => 'nullable|email',
            'buyer_name' => 'nullable|string|max:255',
            'shipping_name' => 'nullable|string|max:255',
            'shipping_address_line1' => 'nullable|string',
            'shipping_city' => 'nullable|string|max:255',
            'shipping_state' => 'nullable|string|max:255',
            'shipping_postal_code' => 'nullable|string|max:50',
            'shipping_country' => 'nullable|string|max:10',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.title' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Calculate totals
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            $shippingCost = $request->input('shipping_cost', 0);
            $tax = $request->input('tax', 0);
            $discount = $request->input('discount', 0);
            $total = $subtotal + $shippingCost + $tax - $discount;

            // Create order
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'sales_channel_id' => $validated['sales_channel_id'],
                'buyer_email' => $validated['buyer_email'] ?? null,
                'buyer_name' => $validated['buyer_name'] ?? null,
                'shipping_name' => $validated['shipping_name'] ?? null,
                'shipping_address_line1' => $validated['shipping_address_line1'] ?? null,
                'shipping_city' => $validated['shipping_city'] ?? null,
                'shipping_state' => $validated['shipping_state'] ?? null,
                'shipping_postal_code' => $validated['shipping_postal_code'] ?? null,
                'shipping_country' => $validated['shipping_country'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'currency' => $request->input('currency', 'USD'),
                'order_status' => 'pending',
                'payment_status' => $request->input('payment_status', 'pending'),
                'fulfillment_status' => 'unfulfilled',
                'order_date' => now(),
            ]);

            // Create order items
            foreach ($validated['items'] as $itemData) {
                $order->items()->create([
                    'product_id' => $itemData['product_id'] ?? null,
                    'title' => $itemData['title'],
                    'sku' => $itemData['sku'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                    'currency' => $request->input('currency', 'USD'),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load('items'),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified order
     */
    public function show(Request $request, string $id)
    {
        $order = Order::with(['items.product', 'metas', 'salesChannel'])->find($id);

        if (!$order) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }
            abort(404, 'Order not found');
        }

        // Prepare meta data for easier access in view
        $metaData = [];
        foreach ($order->metas as $meta) {
            $metaData[$meta->meta_key] = $meta->value_as_array ?? $meta->meta_value;
        }

        // Return JSON if requested via API
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $order,
            ]);
        }

        return view('orders.show', compact('order', 'metaData'));
    }

    /**
     * Show the form for editing the specified order (for web views)
     */
    public function edit(string $id)
    {
        $order = Order::with(['items', 'salesChannel'])->findOrFail($id);
        $salesChannels = SalesChannel::all();

        return view('orders.edit', compact('order', 'salesChannels'));
    }

    /**
     * Update the specified order
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $validated = $request->validate([
            'order_status' => 'nullable|in:pending,processing,shipped,delivered,cancelled,refunded,ready_for_pickup,cancellation_requested',
            'payment_status' => 'nullable|in:pending,paid,refunded,failed,awaiting_payment',
            'fulfillment_status' => 'nullable|in:unfulfilled,partially_fulfilled,fulfilled,ready_for_pickup',
            'shipping_carrier' => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
        ]);

        try {
            // Track status changes
            $statusChanged = false;
            if (isset($validated['order_status']) && $order->order_status !== $validated['order_status']) {
                $statusChanged = true;
            }

            // Update shipped_at if status changes to shipped
            if (isset($validated['order_status']) && $validated['order_status'] === 'shipped' && !$order->shipped_at) {
                $validated['shipped_at'] = now();
            }

            $order->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $order->fresh(['items']),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update order', ['order_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified order
     */
    public function destroy(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        try {
            // Restore inventory if items were deducted
            foreach ($order->items as $item) {
                if ($item->inventory_updated) {
                    $item->restoreInventory();
                }
            }

            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to delete order', ['order_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order by eBay order ID
     */
    public function getByEbayOrderId(string $ebayOrderId): JsonResponse
    {
        $order = Order::with(['items', 'metas'])
            ->where('ebay_order_id', $ebayOrderId)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Update order fulfillment (mark as shipped)
     */
    public function markAsShipped(Request $request, string $id): JsonResponse
    {
        $order = Order::with('salesChannel')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $validated = $request->validate([
            'shipping_carrier' => 'required|string|max:255',
            'tracking_number' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $order->update([
                'shipping_carrier' => $validated['shipping_carrier'],
                'tracking_number' => $validated['tracking_number'],
                'fulfillment_status' => 'fulfilled',
                'order_status' => 'shipped',
                'shipped_at' => now(),
            ]);

            // Deduct inventory for all items
            foreach ($order->items as $item) {
                if (!$item->inventory_updated) {
                    $item->updateInventory();
                }
            }

            DB::commit();

            // Sync shipment to eBay if this is an eBay order
            $ebayResult = null;
            if ($order->isEbayOrder() && !empty($order->ebay_order_id)) {
                $ebayResult = $this->syncShipmentToEbay($order, $validated['shipping_carrier'], $validated['tracking_number']);
            }

            $message = 'Order marked as shipped';
            if ($ebayResult) {
                if ($ebayResult['success']) {
                    $message .= ' and synced to eBay';
                } else {
                    $message .= '. eBay sync failed: ' . ($ebayResult['message'] ?? 'Unknown error');
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $order->fresh(['items']),
                'ebay_sync' => $ebayResult,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark order as shipped', ['order_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark order as shipped: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync shipment tracking to eBay
     */
    protected function syncShipmentToEbay(Order $order, string $shippingCarrier, string $trackingNumber): array
    {
        try {
            $salesChannel = $order->salesChannel;
            if (!$salesChannel || !$salesChannel->isEbay()) {
                return [
                    'success' => false,
                    'message' => 'Not an eBay sales channel',
                ];
            }

            $ebayController = app(EbayController::class);

            // Get item ID and transaction ID from order items for fallback
            $firstItem = $order->items->first();
            $itemId = $firstItem?->ebay_item_id;
            $transactionId = $firstItem?->ebay_transaction_id;

            $result = $ebayController->markOrderAsShipped(
                $salesChannel,
                $order->ebay_order_id,
                $shippingCarrier,
                $trackingNumber,
                $itemId,
                $transactionId
            );

            // Log the sync attempt
            $order->setMeta('ebay_shipment_sync_' . time(), [
                'shipping_carrier' => $shippingCarrier,
                'tracking_number' => $trackingNumber,
                'result' => $result,
                'synced_at' => now()->toIso8601String(),
            ]);

            if ($result['success']) {
                Log::info('Order shipment synced to eBay', [
                    'order_id' => $order->id,
                    'ebay_order_id' => $order->ebay_order_id,
                    'tracking_number' => $trackingNumber,
                ]);
            } else {
                Log::warning('Failed to sync shipment to eBay', [
                    'order_id' => $order->id,
                    'ebay_order_id' => $order->ebay_order_id,
                    'result' => $result,
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Exception syncing shipment to eBay', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel an order
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->order_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Order is already cancelled',
            ], 400);
        }

        try {
            // Restore inventory
            foreach ($order->items as $item) {
                if ($item->inventory_updated) {
                    $item->restoreInventory();
                }
            }

            $order->update([
                'order_status' => 'cancelled',
                'cancel_status' => $request->input('reason', 'Cancelled by user'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $order->fresh(['items']),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to cancel order', ['order_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $salesChannelId = $request->input('sales_channel_id');
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $query = Order::query();

        if ($salesChannelId) {
            $query->where('sales_channel_id', $salesChannelId);
        }

        $query->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo);

        $stats = [
            'total_orders' => (clone $query)->count(),
            'total_revenue' => (clone $query)->where('payment_status', 'paid')->sum('total'),
            'pending_orders' => (clone $query)->where('order_status', 'pending')->count(),
            'processing_orders' => (clone $query)->where('order_status', 'processing')->count(),
            'shipped_orders' => (clone $query)->where('order_status', 'shipped')->count(),
            'delivered_orders' => (clone $query)->where('order_status', 'delivered')->count(),
            'cancelled_orders' => (clone $query)->where('order_status', 'cancelled')->count(),
            'unfulfilled_orders' => (clone $query)->where('fulfillment_status', 'unfulfilled')->count(),
            'average_order_value' => (clone $query)->where('payment_status', 'paid')->avg('total') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ]);
    }

    // ==========================================
    // eBay Order Integration Methods
    // ==========================================

    /**
     * Save order from eBay notification XML
     */
    public function saveFromEbayNotification($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        // Extract the response from SOAP envelope if present
        $response = $xml;
        if ($xml->getName() === 'Envelope') {
            $response = $xml->Body->children()[0] ?? $xml;
        }

        // Get transaction data
        $transaction = null;
        $item = null;

        if (isset($response->TransactionArray->Transaction)) {
            $transaction = $response->TransactionArray->Transaction;
            // If it's an array of transactions, get the first one
            if (is_iterable($transaction) && !isset($transaction->TransactionID)) {
                $transaction = $response->TransactionArray->Transaction[0];
            }
        }

        if (isset($response->Item)) {
            $item = $response->Item;
        }

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in notification', [
                'notification_type' => $notificationType,
                'sales_channel_id' => $salesChannel->id,
            ]);
            return null;
        }

        // Get the order ID from the containing order
        $ebayOrderId = (string) ($transaction->ContainingOrder->OrderID ?? '');
        $extendedOrderId = (string) ($transaction->ContainingOrder->ExtendedOrderID ?? $transaction->ExtendedOrderID ?? $ebayOrderId);

        if (empty($ebayOrderId)) {
            Log::channel('ebay')->warning('No order ID found in notification', [
                'notification_type' => $notificationType,
                'sales_channel_id' => $salesChannel->id,
            ]);
            return null;
        }

        // Check if order already exists
        $order = Order::where('ebay_order_id', $ebayOrderId)->first();

        // Get buyer information
        $buyer = $transaction->Buyer ?? null;
        $shippingAddress = $buyer->BuyerInfo->ShippingAddress ?? null;

        // Calculate totals
        $transactionPrice = (float) ($transaction->TransactionPrice ?? 0);
        $quantity = (int) ($transaction->QuantityPurchased ?? 1);
        $subtotal = $transactionPrice * $quantity;
        $shippingCost = (float) ($transaction->ActualShippingCost ?? $transaction->ShippingServiceSelected->ShippingServiceCost ?? 0);
        $tax = (float) ($transaction->eBayCollectAndRemitTaxes->TotalTaxAmount ?? 0);
        $total = $subtotal + $shippingCost + $tax;
        $currency = (string) ($transaction->TransactionPrice['currencyID'] ?? $transaction->AmountPaid['currencyID'] ?? 'USD');

        // Prepare order data
        $orderData = [
            'sales_channel_id' => $salesChannel->id,
            'ebay_order_id' => $ebayOrderId,
            'ebay_extended_order_id' => $extendedOrderId,

            // Buyer info
            'buyer_username' => (string) ($buyer->UserID ?? ''),
            'buyer_email' => (string) ($buyer->Email ?? ''),
            'buyer_name' => (string) ($shippingAddress->Name ?? ''),
            'buyer_first_name' => (string) ($buyer->UserFirstName ?? ''),
            'buyer_last_name' => (string) ($buyer->UserLastName ?? ''),
            'buyer_phone' => (string) ($shippingAddress->Phone ?? ''),

            // Shipping address
            'shipping_name' => (string) ($shippingAddress->Name ?? ''),
            'shipping_address_line1' => (string) ($shippingAddress->Street1 ?? ''),
            'shipping_address_line2' => (string) ($shippingAddress->Street2 ?? ''),
            'shipping_city' => (string) ($shippingAddress->CityName ?? ''),
            'shipping_state' => (string) ($shippingAddress->StateOrProvince ?? ''),
            'shipping_postal_code' => (string) ($shippingAddress->PostalCode ?? ''),
            'shipping_country' => (string) ($shippingAddress->Country ?? ''),
            'shipping_country_name' => (string) ($shippingAddress->CountryName ?? ''),

            // Totals
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'tax' => $tax,
            'total' => $total,
            'currency' => $currency,

            // Status - Determine local status from eBay data
            'order_status' => $this->determineOrderStatusFromEbay($transaction),
            'payment_status' => $this->determinePaymentStatusFromEbay($transaction),
            'fulfillment_status' => $this->determineFulfillmentStatusFromEbay($transaction),
            'ebay_order_status' => (string) ($transaction->ContainingOrder->OrderStatus ?? 'Completed'),
            'ebay_payment_status' => (string) ($transaction->Status->eBayPaymentStatus ?? ''),
            'cancel_status' => (string) ($transaction->ContainingOrder->CancelStatus ?? ''),

            // Checkout message
            'buyer_checkout_message' => (string) ($transaction->BuyerCheckoutMessage ?? ''),

            // Notification tracking
            'notification_type' => $notificationType,
            'notification_received_at' => $timestamp,

            // Timestamps
            'order_date' => $this->parseEbayDate((string) ($transaction->CreatedDate ?? '')),
            'paid_at' => $this->parseEbayDate((string) ($transaction->PaidTime ?? '')),
            'shipped_at' => $this->parseEbayDate((string) ($transaction->ShippedTime ?? '')),

            // Shipping/tracking info if available
            'tracking_number' => (string) ($transaction->ShippingDetails->ShipmentTrackingDetails->ShipmentTrackingNumber ?? ''),
            'shipping_carrier' => (string) ($transaction->ShippingDetails->ShipmentTrackingDetails->ShippingCarrierUsed ?? ''),

            // Raw data for reference
            'ebay_raw_data' => $this->xmlToArray($xml),
        ];

        try {
            DB::beginTransaction();

            if ($order) {
                // Update existing order
                $order->update($orderData);
                Log::channel('ebay')->info('Order updated from notification', [
                    'order_id' => $order->id,
                    'ebay_order_id' => $ebayOrderId,
                ]);
            } else {
                // Create new order
                $orderData['order_number'] = Order::generateOrderNumber();
                $order = Order::create($orderData);
                Log::channel('ebay')->info('Order created from notification', [
                    'order_id' => $order->id,
                    'ebay_order_id' => $ebayOrderId,
                ]);
            }

            // Save order items
            $this->saveOrderItemFromEbay($order, $transaction, $item);

            // Save additional metadata
            $this->saveOrderMetaFromEbay($order, $transaction, $response);

            DB::commit();

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to save order from notification', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Save order item from eBay transaction
     */
    protected function saveOrderItemFromEbay(Order $order, $transaction, $item = null): OrderItem
    {
        $itemId = (string) ($item->ItemID ?? $transaction->Item->ItemID ?? '');
        $transactionId = (string) ($transaction->TransactionID ?? '');
        $lineItemId = (string) ($transaction->OrderLineItemID ?? '');

        // Check if item already exists
        $orderItem = OrderItem::where('order_id', $order->id)
            ->where('ebay_transaction_id', $transactionId)
            ->first();

        // Try to find matching product by SKU or eBay item ID
        $sku = (string) ($item->SKU ?? $transaction->Item->SKU ?? $transaction->Variation->SKU ?? '');
        $productId = null;

        if (!empty($sku)) {
            $product = Product::where('sku', $sku)->first();
            $productId = $product?->id;
        }

        $itemData = [
            'order_id' => $order->id,
            'product_id' => $productId,
            'ebay_item_id' => $itemId,
            'ebay_transaction_id' => $transactionId,
            'ebay_line_item_id' => $lineItemId,
            'sku' => $sku,
            'title' => (string) ($item->Title ?? $transaction->Item->Title ?? ''),
            'quantity' => (int) ($transaction->QuantityPurchased ?? 1),
            'unit_price' => (float) ($transaction->TransactionPrice ?? 0),
            'total_price' => (float) ($transaction->TransactionPrice ?? 0) * (int) ($transaction->QuantityPurchased ?? 1),
            'actual_shipping_cost' => (float) ($transaction->ActualShippingCost ?? 0),
            'actual_handling_cost' => (float) ($transaction->ActualHandlingCost ?? 0),
            'final_value_fee' => (float) ($transaction->FinalValueFee ?? 0),
            'currency' => (string) ($transaction->TransactionPrice['currencyID'] ?? 'USD'),
            'listing_type' => (string) ($item->ListingType ?? $transaction->Item->ListingType ?? ''),
            'condition_id' => (string) ($item->ConditionID ?? $transaction->Item->ConditionID ?? ''),
            'condition_display_name' => (string) ($item->ConditionDisplayName ?? $transaction->Item->ConditionDisplayName ?? ''),
            'site' => (string) ($item->Site ?? $transaction->Item->Site ?? ''),
            'shipping_service' => (string) ($transaction->ShippingServiceSelected->ShippingService ?? ''),
            'buyer_checkout_message' => (string) ($transaction->BuyerCheckoutMessage ?? ''),
            'item_paid_time' => $this->parseEbayDate((string) ($transaction->PaidTime ?? '')),
            'variation_attributes' => $this->extractVariationAttributes($transaction),
        ];

        if ($orderItem) {
            $orderItem->update($itemData);
        } else {
            $orderItem = OrderItem::create($itemData);
        }

        return $orderItem;
    }

    /**
     * Save order metadata from eBay notification
     */
    protected function saveOrderMetaFromEbay(Order $order, $transaction, $response): void
    {
        // Save seller info
        $seller = $response->Item->Seller ?? $transaction->Item->Seller ?? null;
        if ($seller) {
            $order->setMeta('seller_info', [
                'user_id' => (string) ($seller->UserID ?? ''),
                'email' => (string) ($seller->Email ?? ''),
                'feedback_score' => (int) ($seller->FeedbackScore ?? 0),
            ]);
        }

        // Save full shipping address details
        $buyer = $transaction->Buyer ?? null;
        $shippingAddress = $buyer->BuyerInfo->ShippingAddress ?? null;
        if ($shippingAddress) {
            $order->setMeta('shipping_address', [
                'name' => (string) ($shippingAddress->Name ?? ''),
                'street1' => (string) ($shippingAddress->Street1 ?? ''),
                'street2' => (string) ($shippingAddress->Street2 ?? ''),
                'city' => (string) ($shippingAddress->CityName ?? ''),
                'state' => (string) ($shippingAddress->StateOrProvince ?? ''),
                'postal_code' => (string) ($shippingAddress->PostalCode ?? ''),
                'country' => (string) ($shippingAddress->Country ?? ''),
                'country_name' => (string) ($shippingAddress->CountryName ?? ''),
                'phone' => (string) ($shippingAddress->Phone ?? ''),
                'address_id' => (string) ($shippingAddress->AddressID ?? ''),
                'address_owner' => (string) ($shippingAddress->AddressOwner ?? ''),
                'external_address_id' => (string) ($shippingAddress->ExternalAddressID ?? ''),
            ]);
        }

        // Save monetary details
        if (isset($transaction->MonetaryDetails)) {
            $order->setMeta('monetary_details', $this->xmlToArray($transaction->MonetaryDetails));
        }

        // Save tax details
        if (isset($transaction->eBayCollectAndRemitTaxes)) {
            $order->setMeta('tax_details', $this->xmlToArray($transaction->eBayCollectAndRemitTaxes));
        }

        // Save shipping details
        if (isset($transaction->ShippingDetails)) {
            $order->setMeta('shipping_details', $this->xmlToArray($transaction->ShippingDetails));
        }

        // Save shipping service selected
        if (isset($transaction->ShippingServiceSelected)) {
            $order->setMeta('shipping_service_selected', [
                'shipping_service' => (string) ($transaction->ShippingServiceSelected->ShippingService ?? ''),
                'shipping_service_cost' => (float) ($transaction->ShippingServiceSelected->ShippingServiceCost ?? 0),
                'shipping_service_priority' => (string) ($transaction->ShippingServiceSelected->ShippingServicePriority ?? ''),
                'expedited_service' => (string) ($transaction->ShippingServiceSelected->ExpeditedService ?? ''),
                'shipping_time_min' => (int) ($transaction->ShippingServiceSelected->ShippingTimeMin ?? 0),
                'shipping_time_max' => (int) ($transaction->ShippingServiceSelected->ShippingTimeMax ?? 0),
            ]);
        }

        // Save shipping package info
        if (isset($transaction->ShippingServiceSelected->ShippingPackageInfo)) {
            $order->setMeta('shipping_package_info', $this->xmlToArray($transaction->ShippingServiceSelected->ShippingPackageInfo));
        }

        // Save buyer info
        if (isset($transaction->Buyer)) {
            $order->setMeta('buyer_details', $this->xmlToArray($transaction->Buyer));
        }

        // Save containing order details
        if (isset($transaction->ContainingOrder)) {
            $order->setMeta('containing_order', [
                'order_id' => (string) ($transaction->ContainingOrder->OrderID ?? ''),
                'order_status' => (string) ($transaction->ContainingOrder->OrderStatus ?? ''),
                'cancel_status' => (string) ($transaction->ContainingOrder->CancelStatus ?? ''),
                'checkout_status' => (string) ($transaction->ContainingOrder->CheckoutStatus->Status ?? ''),
                'payment_method' => (string) ($transaction->ContainingOrder->CheckoutStatus->PaymentMethod ?? ''),
            ]);
        }

        // Save transaction status
        if (isset($transaction->Status)) {
            $order->setMeta('transaction_status', [
                'payment_hold_status' => (string) ($transaction->Status->PaymentHoldStatus ?? ''),
                'inquiry_status' => (string) ($transaction->Status->InquiryStatus ?? ''),
                'return_status' => (string) ($transaction->Status->ReturnStatus ?? ''),
                'ebay_payment_status' => (string) ($transaction->Status->eBayPaymentStatus ?? ''),
                'checkout_status' => (string) ($transaction->Status->CheckoutStatus ?? ''),
                'complete_status' => (string) ($transaction->Status->CompleteStatus ?? ''),
            ]);
        }

        // Save payment details
        if (isset($transaction->ExternalTransaction)) {
            $externalTxn = $transaction->ExternalTransaction;
            $order->setMeta('payment_details', [
                'external_transaction_id' => (string) ($externalTxn->ExternalTransactionID ?? ''),
                'external_transaction_status' => (string) ($externalTxn->ExternalTransactionStatus ?? ''),
                'payment_or_refund_amount' => (float) ($externalTxn->PaymentOrRefundAmount ?? 0),
                'external_transaction_time' => (string) ($externalTxn->ExternalTransactionTime ?? ''),
            ]);
        }

        // Save program info (eBay Plus, etc.)
        if (isset($transaction->Program)) {
            $order->setMeta('program_info', $this->xmlToArray($transaction->Program));
        }

        // Save item specifics
        if (isset($response->Item->ItemSpecifics)) {
            $order->setMeta('item_specifics', $this->xmlToArray($response->Item->ItemSpecifics));
        }
    }

    /**
     * Extract variation attributes from transaction
     */
    protected function extractVariationAttributes($transaction): ?array
    {
        if (!isset($transaction->Variation->VariationSpecifics->NameValueList)) {
            return null;
        }

        $attributes = [];
        $nameValueList = $transaction->Variation->VariationSpecifics->NameValueList;

        // Handle both single and multiple NameValueList elements
        if (isset($nameValueList->Name)) {
            $attributes[(string) $nameValueList->Name] = (string) ($nameValueList->Value ?? '');
        } else {
            foreach ($nameValueList as $spec) {
                $attributes[(string) $spec->Name] = (string) ($spec->Value ?? '');
            }
        }

        return !empty($attributes) ? $attributes : null;
    }

    /**
     * Determine local order status from eBay transaction data
     */
    /**
     * Determine local order status from eBay transaction data
     *
     * eBay Status meanings:
     * - Active: Order is in progress (awaiting payment or fulfillment)
     * - Completed: Order has been fulfilled/shipped, NOT delivered
     * - Cancelled/Inactive: Order was cancelled
     *
     * eBay Payment statuses:
     * - NoPaymentFailure: No payment failure occurred (does NOT mean paid)
     * - PaymentComplete: Payment has been received
     *
     * eBay CompleteStatus:
     * - Incomplete: Order is not yet complete (checkout not finished, payment pending)
     * - Complete: Order checkout and payment are complete
     *
     * Note: eBay does not provide a "Delivered" status in the Trading API.
     * Only mark as delivered when we explicitly receive a delivery notification.
     */
    protected function determineOrderStatusFromEbay($transaction): string
    {
        // Check for cancellation first
        $cancelStatus = (string) ($transaction->ContainingOrder->CancelStatus ?? '');
        if (!empty($cancelStatus) && strtolower($cancelStatus) !== 'none' && strtolower($cancelStatus) !== 'notapplicable') {
            if (in_array(strtolower($cancelStatus), ['cancelled', 'cancelcomplete', 'cancelcompleted'])) {
                return 'cancelled';
            }
            if (in_array(strtolower($cancelStatus), ['cancelrequested', 'cancelrequest', 'cancelpending'])) {
                return 'cancellation_requested';
            }
        }

        // Check if shipped
        $shippedTime = (string) ($transaction->ShippedTime ?? '');
        if (!empty($shippedTime)) {
            return 'shipped';
        }

        // Check eBay order status
        $ebayOrderStatus = strtolower((string) ($transaction->ContainingOrder->OrderStatus ?? ''));

        // eBay "Completed" means shipped/fulfilled, NOT delivered
        if (in_array($ebayOrderStatus, ['completed', 'complete'])) {
            return 'shipped';
        }

        if (in_array($ebayOrderStatus, ['shipped'])) {
            return 'shipped';
        }

        if (in_array($ebayOrderStatus, ['cancelled', 'inactive'])) {
            return 'cancelled';
        }

        // For Active orders, determine status based on payment
        $completeStatus = strtolower((string) ($transaction->Status->CompleteStatus ?? ''));
        $checkoutStatus = strtolower((string) ($transaction->Status->CheckoutStatus ?? ''));
        $paidTime = (string) ($transaction->PaidTime ?? '');
        $paymentStatus = strtolower((string) ($transaction->Status->eBayPaymentStatus ?? ''));

        // Order is processing only if payment is actually confirmed
        if (!empty($paidTime) || $paymentStatus === 'paymentcomplete' || $checkoutStatus === 'checkoutcomplete' || $completeStatus === 'complete') {
            return 'processing';
        }

        // Unpaid / checkout incomplete → pending
        return 'pending';
    }

    /**
     * Determine local payment status from eBay transaction data
     *
     * eBay eBayPaymentStatus meanings:
     * - NoPaymentFailure: No payment failure occurred (does NOT mean paid!)
     * - PaymentComplete: Payment has been received (actually paid)
     * - PaymentPending: Payment is pending
     * - PaymentFailed: Payment attempt failed
     *
     * Reliable indicators of payment:
     * - PaidTime is set: Confirmed paid
     * - CheckoutStatus = CheckoutComplete: Checkout (including payment) is done
     * - CompleteStatus = Complete: Transaction is complete
     * - eBayPaymentStatus = PaymentComplete: Payment confirmed
     */
    protected function determinePaymentStatusFromEbay($transaction): string
    {
        $paymentStatus = strtolower((string) ($transaction->Status->eBayPaymentStatus ?? ''));
        $paidTime = (string) ($transaction->PaidTime ?? '');
        $checkoutStatus = strtolower((string) ($transaction->Status->CheckoutStatus ?? ''));
        $completeStatus = strtolower((string) ($transaction->Status->CompleteStatus ?? ''));

        // Check refund status
        $refundStatus = (string) ($transaction->MonetaryDetails->Refunds->Refund->RefundStatus ?? '');
        if (!empty($refundStatus) && strtolower($refundStatus) === 'successful') {
            return 'refunded';
        }

        // Paid if PaidTime is explicitly set
        if (!empty($paidTime)) {
            return 'paid';
        }

        // PaymentComplete explicitly means paid
        if (in_array($paymentStatus, ['paymentcomplete', 'paid'])) {
            return 'paid';
        }

        // CheckoutComplete means buyer completed checkout (including payment)
        if ($checkoutStatus === 'checkoutcomplete') {
            return 'paid';
        }

        // CompleteStatus = Complete means the transaction is fully complete
        if ($completeStatus === 'complete') {
            return 'paid';
        }

        // Payment failed
        if (in_array($paymentStatus, ['paymentfailed', 'failed'])) {
            return 'failed';
        }

        // NoPaymentFailure with CheckoutIncomplete = buyer hasn't paid yet
        // NoPaymentFailure only means "no failure occurred", NOT that payment was received
        return 'pending';
    }

    /**
     * Determine local fulfillment status from eBay transaction data
     */
    protected function determineFulfillmentStatusFromEbay($transaction): string
    {
        // Check if shipped
        $shippedTime = (string) ($transaction->ShippedTime ?? '');
        if (!empty($shippedTime)) {
            return 'fulfilled';
        }

        // Check pickup status
        $pickupStatus = (string) ($transaction->PickupDetails->PickupStatus ?? '');
        if (!empty($pickupStatus)) {
            if (strtolower($pickupStatus) === 'readyforpickup') {
                return 'ready_for_pickup';
            }
            if (strtolower($pickupStatus) === 'pickedup') {
                return 'fulfilled';
            }
        }

        // Check for partial fulfillment in multi-item orders
        $quantityPurchased = (int) ($transaction->QuantityPurchased ?? 1);
        $quantityShipped = (int) ($transaction->ShippingDetails->QuantityShipped ?? 0);

        if ($quantityShipped > 0 && $quantityShipped < $quantityPurchased) {
            return 'partially_fulfilled';
        }

        if ($quantityShipped >= $quantityPurchased && $quantityShipped > 0) {
            return 'fulfilled';
        }

        return 'unfulfilled';
    }

    /**
     * Parse eBay date string to Carbon instance
     */
    protected function parseEbayDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Convert XML node to array
     */
    protected function xmlToArray($node): array|string
    {
        $result = [];

        // Get attributes
        foreach ($node->attributes() as $attrName => $attrValue) {
            $result['@' . $attrName] = (string) $attrValue;
        }

        // Get child elements
        $children = $node->children();

        if ($children->count() === 0) {
            $text = trim((string) $node);
            if (!empty($result)) {
                if (!empty($text)) {
                    $result['@value'] = $text;
                }
                return $result;
            }
            return $text;
        }

        // Process children
        $childArray = [];
        foreach ($children as $childName => $childNode) {
            $childValue = $this->xmlToArray($childNode);

            if (isset($childArray[$childName])) {
                if (!is_array($childArray[$childName]) || !isset($childArray[$childName][0])) {
                    $childArray[$childName] = [$childArray[$childName]];
                }
                $childArray[$childName][] = $childValue;
            } else {
                $childArray[$childName] = $childValue;
            }
        }

        return array_merge($result, $childArray);
    }

    /**
     * Check if notification type is order-related
     */
    public static function isOrderNotification(string $notificationType): bool
    {
        $orderNotificationTypes = [
            // New order notifications
            'FixedPriceTransaction',
            'AuctionCheckoutComplete',
            'ItemSold',
            'GetItemTransactionsResponse',
            // Order status update notifications - Shipping
            'ItemMarkedShipped',
            'ItemMarkedAsDispatched',  // Alternative shipping notification
            'ItemShipped',             // Another shipping notification type
            // Order status update notifications - Payment
            'ItemMarkedPaid',
            'ItemMarkedAsPaid',        // Alternative payment notification
            // Delivery notifications
            'ItemDelivered',
            'ItemMarkedAsDelivered',
            // Pickup notifications
            'ItemReadyForPickup',
            'ItemPickedUp',
            // Cancellation and checkout notifications
            'BuyerCancelRequested',
            'OrderCancelled',
            'CheckoutBuyerRequestsTotal',
            'PaymentReminder',
            // Refund notifications
            'RefundInitiated',
            'RefundCompleted',
        ];

        return in_array($notificationType, $orderNotificationTypes);
    }

    /**
     * Check if notification is for a new order
     */
    public static function isNewOrderNotification(string $notificationType): bool
    {
        return in_array($notificationType, [
            'FixedPriceTransaction',
            'AuctionCheckoutComplete',
            'ItemSold',
            'GetItemTransactionsResponse',
        ]);
    }

    /**
     * Process eBay order notification based on event type
     */
    public function processEbayNotification($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        // Route to appropriate handler based on notification type
        return match ($notificationType) {
            // New order notifications
            'FixedPriceTransaction',
            'AuctionCheckoutComplete',
            'ItemSold',
            'GetItemTransactionsResponse' => $this->saveFromEbayNotification($xml, $salesChannel, $notificationType, $timestamp),

            // Shipping notifications
            'ItemMarkedShipped',
            'ItemMarkedAsDispatched',
            'ItemShipped' => $this->handleItemMarkedShipped($xml, $salesChannel, $notificationType, $timestamp),

            // Payment notifications
            'ItemMarkedPaid',
            'ItemMarkedAsPaid' => $this->handleItemMarkedPaid($xml, $salesChannel, $notificationType, $timestamp),

            // Delivery notifications
            'ItemDelivered',
            'ItemMarkedAsDelivered' => $this->handleItemDelivered($xml, $salesChannel, $notificationType, $timestamp),

            // Pickup notifications
            'ItemReadyForPickup' => $this->handleItemReadyForPickup($xml, $salesChannel, $notificationType, $timestamp),
            'ItemPickedUp' => $this->handleItemPickedUp($xml, $salesChannel, $notificationType, $timestamp),

            // Cancellation notifications
            'BuyerCancelRequested',
            'OrderCancelled' => $this->handleBuyerCancelRequested($xml, $salesChannel, $notificationType, $timestamp),

            // Refund notifications
            'RefundInitiated',
            'RefundCompleted' => $this->handleRefund($xml, $salesChannel, $notificationType, $timestamp),

            // Other checkout notifications
            'CheckoutBuyerRequestsTotal' => $this->handleCheckoutBuyerRequestsTotal($xml, $salesChannel, $notificationType, $timestamp),
            'PaymentReminder' => $this->handlePaymentReminder($xml, $salesChannel, $notificationType, $timestamp),

            default => null,
        };
    }

    /**
     * Handle ItemMarkedShipped notification - Mark order as shipped with tracking
     */
    protected function handleItemMarkedShipped($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($xml);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in ItemMarkedShipped notification', [
                'notification_type' => $notificationType,
                'sales_channel_id' => $salesChannel->id,
            ]);
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            Log::channel('ebay')->warning('No order ID found in ItemMarkedShipped notification');
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            Log::channel('ebay')->warning('Order not found for ItemMarkedShipped', ['ebay_order_id' => $ebayOrderId]);
            return null;
        }

        try {
            DB::beginTransaction();

            // Extract shipping/tracking info
            $shippingDetails = $transaction->ShippingDetails ?? null;
            $shipmentTrackingDetails = $shippingDetails->ShipmentTrackingDetails ?? null;

            $trackingNumber = (string) ($shipmentTrackingDetails->ShipmentTrackingNumber ?? '');
            $shippingCarrier = (string) ($shipmentTrackingDetails->ShippingCarrierUsed ?? '');
            $shippedTime = $this->parseEbayDate((string) ($transaction->ShippedTime ?? ''));

            // Update order
            $order->update([
                'order_status' => 'shipped',
                'fulfillment_status' => 'fulfilled',
                'tracking_number' => $trackingNumber ?: $order->tracking_number,
                'shipping_carrier' => $shippingCarrier ?: $order->shipping_carrier,
                'shipped_at' => $shippedTime ?? now(),
                'ebay_order_status' => (string) ($transaction->ContainingOrder->OrderStatus ?? $order->ebay_order_status),
            ]);

            // Save shipment tracking details to meta
            if ($shipmentTrackingDetails) {
                $order->setMeta('shipment_tracking', [
                    'tracking_number' => $trackingNumber,
                    'shipping_carrier' => $shippingCarrier,
                    'shipped_time' => (string) ($transaction->ShippedTime ?? ''),
                ]);
            }

            // Update inventory for all items if not already done
            foreach ($order->items as $item) {
                if (!$item->inventory_updated) {
                    $item->updateInventory();
                }
            }

            // Log the event
            $order->setMeta('event_log_' . time(), [
                'event' => 'ItemMarkedShipped',
                'timestamp' => $timestamp,
                'tracking_number' => $trackingNumber,
                'shipping_carrier' => $shippingCarrier,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as shipped', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
                'tracking_number' => $trackingNumber,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process ItemMarkedShipped', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle ItemMarkedPaid notification - Mark order as paid
     */
    protected function handleItemMarkedPaid($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($xml);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in ItemMarkedPaid notification');
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            // Order doesn't exist yet, create it
            return $this->saveFromEbayNotification($xml, $salesChannel, $notificationType, $timestamp);
        }

        try {
            DB::beginTransaction();

            $paidTime = $this->parseEbayDate((string) ($transaction->PaidTime ?? ''));

            $order->update([
                'payment_status' => 'paid',
                'paid_at' => $paidTime ?? now(),
                'ebay_payment_status' => (string) ($transaction->Status->eBayPaymentStatus ?? 'NoPaymentFailure'),
            ]);

            // Log the event
            $order->setMeta('event_log_' . time(), [
                'event' => 'ItemMarkedPaid',
                'timestamp' => $timestamp,
                'paid_time' => $paidTime?->toIso8601String(),
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as paid', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process ItemMarkedPaid', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle ItemReadyForPickup notification - Mark order ready for pickup
     */
    protected function handleItemReadyForPickup($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($xml);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in ItemReadyForPickup notification');
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            Log::channel('ebay')->warning('Order not found for ItemReadyForPickup', ['ebay_order_id' => $ebayOrderId]);
            return null;
        }

        try {
            DB::beginTransaction();

            $order->update([
                'order_status' => 'ready_for_pickup',
                'fulfillment_status' => 'ready_for_pickup',
                'ebay_order_status' => (string) ($transaction->ContainingOrder->OrderStatus ?? $order->ebay_order_status),
            ]);

            // Save pickup details to meta
            $pickupDetails = $transaction->PickupDetails ?? null;
            if ($pickupDetails) {
                $order->setMeta('pickup_details', $this->xmlToArray($pickupDetails));
            }

            // Log the event
            $order->setMeta('event_log_' . time(), [
                'event' => 'ItemReadyForPickup',
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as ready for pickup', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process ItemReadyForPickup', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle BuyerCancelRequested notification - Process cancellation request
     */
    protected function handleBuyerCancelRequested($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($xml);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in BuyerCancelRequested notification');
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            Log::channel('ebay')->warning('Order not found for BuyerCancelRequested', ['ebay_order_id' => $ebayOrderId]);
            return null;
        }

        try {
            DB::beginTransaction();

            $cancelReason = (string) ($transaction->ContainingOrder->CancelReason ??
                            $transaction->CancelReason ?? 'Buyer requested cancellation');
            $cancelStatus = (string) ($transaction->ContainingOrder->CancelStatus ?? 'CancelRequested');

            $order->update([
                'order_status' => 'cancellation_requested',
                'cancel_status' => $cancelStatus,
            ]);

            // Save cancellation details to meta
            $order->setMeta('cancellation_request', [
                'requested_at' => $timestamp,
                'reason' => $cancelReason,
                'cancel_status' => $cancelStatus,
                'requested_by' => 'buyer',
            ]);

            // Log the event
            $order->setMeta('event_log_' . time(), [
                'event' => 'BuyerCancelRequested',
                'timestamp' => $timestamp,
                'reason' => $cancelReason,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order cancellation requested by buyer', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
                'reason' => $cancelReason,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process BuyerCancelRequested', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle CheckoutBuyerRequestsTotal notification
     */
    protected function handleCheckoutBuyerRequestsTotal($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($xml);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in CheckoutBuyerRequestsTotal notification');
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);

        // This is typically a pre-checkout event, the order might not exist yet
        // Just log it and save to meta if order exists
        if (!empty($ebayOrderId)) {
            $order = Order::where('ebay_order_id', $ebayOrderId)->first();
            if ($order) {
                $order->setMeta('event_log_' . time(), [
                    'event' => 'CheckoutBuyerRequestsTotal',
                    'timestamp' => $timestamp,
                ]);

                Log::channel('ebay')->info('Checkout total requested', [
                    'order_id' => $order->id,
                    'ebay_order_id' => $ebayOrderId,
                ]);

                return $order;
            }
        }

        Log::channel('ebay')->info('CheckoutBuyerRequestsTotal received (no existing order)', [
            'notification_type' => $notificationType,
        ]);

        return null;
    }

    /**
     * Handle PaymentReminder notification
     */
    protected function handlePaymentReminder($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($xml);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in PaymentReminder notification');
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            // Create order if it doesn't exist
            return $this->saveFromEbayNotification($xml, $salesChannel, $notificationType, $timestamp);
        }

        try {
            // Update payment status if still pending
            if ($order->payment_status === 'pending') {
                $order->update([
                    'payment_status' => 'awaiting_payment',
                ]);
            }

            // Log the reminder
            $order->setMeta('event_log_' . time(), [
                'event' => 'PaymentReminder',
                'timestamp' => $timestamp,
            ]);

            Log::channel('ebay')->info('Payment reminder received', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            Log::channel('ebay')->error('Failed to process PaymentReminder', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle ItemDelivered notification - Mark order as delivered
     */
    protected function handleItemDelivered($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($xml);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in ItemDelivered notification', [
                'notification_type' => $notificationType,
                'sales_channel_id' => $salesChannel->id,
            ]);
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            Log::channel('ebay')->warning('No order ID found in ItemDelivered notification');
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            Log::channel('ebay')->warning('Order not found for ItemDelivered', ['ebay_order_id' => $ebayOrderId]);
            return null;
        }

        try {
            DB::beginTransaction();

            // Update order to delivered status
            $order->update([
                'order_status' => 'delivered',
                'fulfillment_status' => 'fulfilled',
                'ebay_order_status' => (string) ($transaction->ContainingOrder->OrderStatus ?? 'Completed'),
            ]);

            // Log the event
            $order->setMeta('event_log_' . time(), [
                'event' => $notificationType,
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as delivered', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process ItemDelivered', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle ItemPickedUp notification - Mark order as picked up (fulfilled)
     */
    protected function handleItemPickedUp($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($xml);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in ItemPickedUp notification', [
                'notification_type' => $notificationType,
                'sales_channel_id' => $salesChannel->id,
            ]);
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            Log::channel('ebay')->warning('No order ID found in ItemPickedUp notification');
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            Log::channel('ebay')->warning('Order not found for ItemPickedUp', ['ebay_order_id' => $ebayOrderId]);
            return null;
        }

        try {
            DB::beginTransaction();

            // Update order to delivered status (picked up = delivered for local pickup)
            $order->update([
                'order_status' => 'delivered',
                'fulfillment_status' => 'fulfilled',
                'ebay_order_status' => (string) ($transaction->ContainingOrder->OrderStatus ?? 'Completed'),
            ]);

            // Log the event
            $order->setMeta('event_log_' . time(), [
                'event' => $notificationType,
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as picked up', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process ItemPickedUp', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle Refund notifications - Mark order as refunded
     */
    protected function handleRefund($xml, SalesChannel $salesChannel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($xml);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in Refund notification', [
                'notification_type' => $notificationType,
                'sales_channel_id' => $salesChannel->id,
            ]);
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            Log::channel('ebay')->warning('No order ID found in Refund notification');
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            Log::channel('ebay')->warning('Order not found for Refund', ['ebay_order_id' => $ebayOrderId]);
            return null;
        }

        try {
            DB::beginTransaction();

            // Extract refund details
            $refundAmount = (float) ($transaction->MonetaryDetails->Refunds->Refund->RefundAmount ?? 0);
            $refundStatus = (string) ($transaction->MonetaryDetails->Refunds->Refund->RefundStatus ?? '');
            $refundTime = $this->parseEbayDate((string) ($transaction->MonetaryDetails->Refunds->Refund->RefundTime ?? ''));

            // Update order status based on notification type
            $isCompleted = $notificationType === 'RefundCompleted' || strtolower($refundStatus) === 'successful';

            if ($isCompleted) {
                $order->update([
                    'order_status' => 'refunded',
                    'payment_status' => 'refunded',
                ]);
            }

            // Log the refund event
            $order->setMeta('event_log_' . time(), [
                'event' => $notificationType,
                'timestamp' => $timestamp,
                'refund_amount' => $refundAmount,
                'refund_status' => $refundStatus,
                'refund_time' => $refundTime?->toIso8601String(),
            ]);

            // Restore inventory if refund is complete
            if ($isCompleted) {
                foreach ($order->items as $item) {
                    if ($item->inventory_updated) {
                        $item->restoreInventory();
                    }
                }
            }

            DB::commit();

            Log::channel('ebay')->info('Order refund processed', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
                'notification_type' => $notificationType,
                'refund_amount' => $refundAmount,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process Refund notification', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract response from SOAP envelope
     */
    protected function extractResponseFromEnvelope($xml)
    {
        if ($xml->getName() === 'Envelope') {
            return $xml->Body->children()[0] ?? $xml;
        }
        return $xml;
    }

    /**
     * Extract transaction from response
     */
    protected function extractTransaction($response)
    {
        if (isset($response->TransactionArray->Transaction)) {
            $transaction = $response->TransactionArray->Transaction;
            // If it's an array of transactions, get the first one
            if (is_iterable($transaction) && !isset($transaction->TransactionID)) {
                return $response->TransactionArray->Transaction[0];
            }
            return $transaction;
        }
        return null;
    }

    /**
     * Extract order ID from transaction
     */
    protected function extractOrderId($transaction): string
    {
        return (string) ($transaction->ContainingOrder->OrderID ??
                        $transaction->ContainingOrder->ExtendedOrderID ??
                        $transaction->ExtendedOrderID ?? '');
    }
}
