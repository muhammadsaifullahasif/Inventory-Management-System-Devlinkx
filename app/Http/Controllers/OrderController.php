<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\OrderMeta;
use App\Models\Shipping;
use App\Models\SalesChannel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\ShippingService;

class OrderController extends Controller
{
    protected ShippingService $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

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

        // Get active carriers for the ship modal
        $shippingCarriers = Shipping::where('active_status', '1')
            ->where('delete_status', '0')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_default']);

        // Return JSON if requested via API
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);
        }

        return view('orders.index', compact('orders', 'salesChannels', 'shippingCarriers'));
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

            // Validate shipping address if a carrier has address validation enabled
            if ($order->shipping_address_line1) {
                $this->shippingService->validateOrderAddress($order);
            }

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
        $order = Order::with(['items.product.product_meta', 'metas', 'salesChannel'])->find($id);

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

        $shippingCarriers = Shipping::where('active_status', '1')
            ->where('delete_status', '0')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_default']);

        return view('orders.show', compact('order', 'metaData', 'shippingCarriers'));
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
     * Return order items with product dimensions for the rate-quote modal.
     * Used by the orders/index ship modal to populate the items table via AJAX.
     */
    public function rateInfo(string $id): JsonResponse
    {
        $order = Order::with(['items.product.product_meta'])->findOrFail($id);

        $items = $order->items->map(function ($item) {
            $meta = $item->product?->product_meta ?? [];
            return [
                'order_item_id' => $item->id,
                'title'         => $item->title,
                'sku'           => $item->sku ?? 'N/A',
                'quantity'      => (int) $item->quantity,
                'weight'        => (float) ($meta['weight'] ?? 0),
                'length'        => (float) ($meta['length'] ?? 0),
                'width'         => (float) ($meta['width']  ?? 0),
                'height'        => (float) ($meta['height'] ?? 0),
            ];
        });

        return response()->json(['success' => true, 'items' => $items]);
    }

    /**
     * Get estimated shipping rates for an order from a specific carrier.
     * Does NOT create a shipment — read-only cost estimate only.
     */
    public function getShippingRates(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'                  => 'required|integer|exists:orders,id',
            'carrier_id'                => 'required|integer|exists:shippings,id',
            'items'                     => 'nullable|array',
            'items.*.order_item_id'     => 'nullable|integer',
            'items.*.weight'            => 'nullable|numeric|min:0',
            'items.*.length'            => 'nullable|numeric|min:0',
            'items.*.width'             => 'nullable|numeric|min:0',
            'items.*.height'            => 'nullable|numeric|min:0',
        ]);

        $order        = Order::with(['items.product.product_meta'])->findOrFail($request->order_id);
        $carrier      = Shipping::findOrFail($request->carrier_id);
        $itemOverrides = $request->input('items', []);

        try {
            $rates = $this->shippingService->getRatesForOrder($order, $carrier, $itemOverrides);

            $shipperAddress = implode(', ', array_filter([
                $carrier->shipper_name,
                $carrier->shipper_address,
                $carrier->shipper_city,
                $carrier->shipper_state,
                $carrier->shipper_postal_code,
                $carrier->shipper_country,
            ]));

            return response()->json([
                'success'        => true,
                'rates'          => $rates,
                'shipper'        => $shipperAddress ?: null,
                'carrier_name'   => $carrier->name,
            ]);
        } catch (\Throwable $e) {
            Log::error('getShippingRates failed', ['order_id' => $request->order_id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
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

    /**
     * Generate a shipping label for an order.
     * Creates a shipment with the carrier, stores the label, and marks the order as shipped.
     */
    public function generateShippingLabel(Request $request, string $id): JsonResponse
    {
        $order = Order::with(['items.product.product_meta', 'salesChannel'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $validated = $request->validate([
            'carrier_id'                => 'required|integer|exists:shippings,id',
            'service_code'              => 'required|string',
            'items'                     => 'nullable|array',
            'items.*.order_item_id'     => 'nullable|integer',
            'items.*.weight'            => 'nullable|numeric|min:0',
            'items.*.length'            => 'nullable|numeric|min:0',
            'items.*.width'             => 'nullable|numeric|min:0',
            'items.*.height'            => 'nullable|numeric|min:0',
        ]);

        $carrier      = Shipping::findOrFail($validated['carrier_id']);
        $serviceCode  = $validated['service_code'];
        $itemOverrides = $validated['items'] ?? [];

        try {
            DB::beginTransaction();

            // Generate label via ShippingService
            $labelResult = $this->shippingService->generateLabelForOrder(
                $order,
                $carrier,
                $serviceCode,
                $itemOverrides
            );

            $trackingNumber = $labelResult['tracking_number'];
            $labelPath      = $labelResult['label_path'];
            $carrierName    = $labelResult['carrier_name'];

            // Update order with shipping info and mark as shipped
            $order->update([
                'shipping_carrier'     => $carrierName,
                'tracking_number'      => $trackingNumber,
                'shipping_label_path'  => $labelPath,
                'label_generated_at'   => now(),
                'fulfillment_status'   => 'fulfilled',
                'order_status'         => 'shipped',
                'shipped_at'           => now(),
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
                $ebayResult = $this->syncShipmentToEbay($order, $carrierName, $trackingNumber);
            }

            $message = 'Shipping label generated and order marked as shipped';
            if ($ebayResult) {
                if ($ebayResult['success']) {
                    $message .= ' and synced to eBay';
                } else {
                    $message .= '. eBay sync failed: ' . ($ebayResult['message'] ?? 'Unknown error');
                }
            }

            return response()->json([
                'success'         => true,
                'message'         => $message,
                'tracking_number' => $trackingNumber,
                'label_url'       => route('orders.label', $order->id),
                'data'            => $order->fresh(['items']),
                'ebay_sync'       => $ebayResult,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate shipping label', [
                'order_id' => $id,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate shipping label: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download the shipping label for an order.
     */
    public function downloadLabel(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            abort(404, 'Order not found');
        }

        if (!$order->shipping_label_path) {
            abort(404, 'No shipping label found for this order');
        }

        if (!Storage::exists($order->shipping_label_path)) {
            abort(404, 'Shipping label file not found');
        }

        $filename = "label-{$order->order_number}.pdf";

        return Storage::download($order->shipping_label_path, $filename);
    }
}
