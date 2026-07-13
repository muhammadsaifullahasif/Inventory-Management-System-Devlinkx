<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderReturnController extends Controller
{
    /**
     * Create a manual return for an order (whole order or specific items).
     */
    public function store(Request $request, string $orderId): JsonResponse
    {
        $order = Order::with('items')->find($orderId);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
            'buyer_comments' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer|exists:order_items,id',
        ]);

        // Validate every order_item_id actually belongs to this order
        $orderItemIds = $order->items->pluck('id');
        foreach ($validated['items'] as $row) {
            if (!$orderItemIds->contains($row['order_item_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Order item {$row['order_item_id']} does not belong to this order",
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $orderReturn = OrderReturn::create([
                'order_id' => $order->id,
                'sales_channel_id' => $order->sales_channel_id,
                'source' => 'manual',
                'status' => 'requested',
                'reason' => $validated['reason'],
                'buyer_comments' => $validated['buyer_comments'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'requested_at' => now(),
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $row) {
                $orderItem = OrderItem::find($row['order_item_id']);

                OrderReturnItem::create([
                    'order_return_id' => $orderReturn->id,
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'quantity' => $orderItem->quantity,
                ]);
            }

            $order->update([
                'return_status' => $order->return_status ?? 'requested',
                'return_reason' => $order->return_reason ?? $validated['reason'],
                'return_requested_at' => $order->return_requested_at ?? now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Return created successfully',
                'data' => $orderReturn->load('items.orderItem'),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order return', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create return: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a manual return request (status only, no inventory change).
     */
    public function approve(string $returnId): JsonResponse
    {
        $orderReturn = OrderReturn::find($returnId);

        if (!$orderReturn) {
            return response()->json(['success' => false, 'message' => 'Return not found'], 404);
        }

        $orderReturn->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $orderReturn->order?->update(['return_status' => 'approved']);

        return response()->json(['success' => true, 'message' => 'Return approved', 'data' => $orderReturn]);
    }

    /**
     * Decline a manual return request.
     */
    public function decline(Request $request, string $returnId): JsonResponse
    {
        $orderReturn = OrderReturn::find($returnId);

        if (!$orderReturn) {
            return response()->json(['success' => false, 'message' => 'Return not found'], 404);
        }

        $orderReturn->update([
            'status' => 'declined',
            'notes' => $request->input('reason', $orderReturn->notes),
        ]);

        $orderReturn->order?->update(['return_status' => 'declined']);

        return response()->json(['success' => true, 'message' => 'Return declined', 'data' => $orderReturn]);
    }

    /**
     * Mark items as received and restock inventory.
     * This is the only action that restores stock — refunds never trigger a restock on their own.
     */
    public function markReceived(string $returnId): JsonResponse
    {
        $orderReturn = OrderReturn::with('items.orderItem')->find($returnId);

        if (!$orderReturn) {
            return response()->json(['success' => false, 'message' => 'Return not found'], 404);
        }

        try {
            DB::beginTransaction();

            foreach ($orderReturn->items as $returnItem) {
                $returnItem->restock();
            }

            $orderReturn->update([
                'status' => 'item_received',
                'received_at' => now(),
            ]);

            $orderReturn->order?->update(['return_status' => 'return_received']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Items marked received and inventory restocked',
                'data' => $orderReturn->fresh('items.orderItem'),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark return received', ['return_id' => $returnId, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark received: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Close a return case.
     */
    public function close(Request $request, string $returnId): JsonResponse
    {
        $orderReturn = OrderReturn::find($returnId);

        if (!$orderReturn) {
            return response()->json(['success' => false, 'message' => 'Return not found'], 404);
        }

        $orderReturn->update([
            'status' => 'closed',
            'closed_at' => now(),
            'notes' => $request->input('notes', $orderReturn->notes),
        ]);

        $orderReturn->order?->update(['return_closed_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Return closed', 'data' => $orderReturn]);
    }
}
