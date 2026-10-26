<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\EbayController;
use App\Http\Controllers\InventorySyncController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'throttle:api']);

/*
|--------------------------------------------------------------------------
| eBay Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle eBay notifications:
| - GET: Challenge verification (Commerce Notification API)
| - POST: Actual notifications (both Platform and Commerce API)
|
*/

// Single webhook endpoint for ALL eBay notifications (no channel ID needed)
// eBay Platform Notifications only supports ONE webhook URL per application.
// The handler routes to the correct channel based on RecipientUserID in the notification.
Route::match(['get', 'post'], '/ebay/webhook', [EbayController::class, 'handleEbayWebhook'])
    ->middleware('throttle:ebay-webhook')
    ->name('ebay.webhook');

// Legacy routes (for backwards compatibility with existing subscriptions)
Route::match(['get', 'post'], '/ebay/webhook/{id}', [EbayController::class, 'handleEbayOrderWebhook'])
    ->middleware('throttle:ebay-webhook')
    ->name('ebay.webhook.legacy');

Route::post('/ebay/orders/webhook/{id}', [EbayController::class, 'handleEbayOrderWebhook'])
    ->middleware('throttle:ebay-webhook')
    ->name('ebay.orders.webhook');

// Test endpoint to verify webhook is reachable
Route::get('/ebay/webhook-test', function (Request $request) {

    return response()->json([
        'success' => true,
        'message' => 'Webhook endpoint is reachable',
        'timestamp' => now()->toIso8601String(),
        'webhook_url' => url("/api/ebay/webhook"),
    ]);
})->middleware('throttle:ebay-webhook')->name('ebay.webhook.test');

/*
|--------------------------------------------------------------------------
| eBay Returns Management Routes
|--------------------------------------------------------------------------
*/
Route::prefix('ebay')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Returns
    Route::get('/returns/{id}', [EbayController::class, 'getReturns'])->name('ebay.returns.index')->middleware('permission:view orders');
    Route::get('/returns/{id}/{returnId}', [EbayController::class, 'getReturnDetails'])->name('ebay.returns.show')->middleware('permission:view orders');
    Route::post('/returns/{id}/{returnId}/approve', [EbayController::class, 'approveReturn'])->name('ebay.returns.approve')->middleware('permission:edit orders');
    Route::post('/returns/{id}/{returnId}/decline', [EbayController::class, 'declineReturn'])->name('ebay.returns.decline')->middleware('permission:edit orders');
    Route::post('/returns/{id}/{returnId}/shipping-label', [EbayController::class, 'provideReturnShippingLabel'])->name('ebay.returns.shipping-label')->middleware('permission:edit orders');
    Route::post('/returns/{id}/{returnId}/mark-received', [EbayController::class, 'markReturnReceived'])->name('ebay.returns.mark-received')->middleware('permission:edit orders');
    Route::post('/returns/{id}/{returnId}/refund', [EbayController::class, 'issueReturnRefund'])->name('ebay.returns.refund')->middleware('permission:edit orders');
    Route::post('/returns/{id}/{returnId}/close', [EbayController::class, 'closeReturn'])->name('ebay.returns.close')->middleware('permission:edit orders');

    // Cancellations
    Route::get('/cancellations/{id}', [EbayController::class, 'getCancellations'])->name('ebay.cancellations.index')->middleware('permission:view orders');
    Route::post('/cancellations/{id}/{orderId}/approve', [EbayController::class, 'approveCancellation'])->name('ebay.cancellations.approve')->middleware('permission:edit orders');
    Route::post('/cancellations/{id}/{orderId}/reject', [EbayController::class, 'rejectCancellation'])->name('ebay.cancellations.reject')->middleware('permission:edit orders');
    Route::post('/cancellations/{id}/{orderId}/create', [EbayController::class, 'createCancellation'])->name('ebay.cancellations.create')->middleware('permission:edit orders');

    // Refunds
    Route::post('/refunds/{id}/{orderId}', [EbayController::class, 'issueRefund'])->name('ebay.refunds.issue')->middleware('permission:edit orders');
    Route::post('/refunds/{id}/{orderId}/partial', [EbayController::class, 'issuePartialRefund'])->name('ebay.refunds.partial')->middleware('permission:edit orders');

    // Inquiries (INR - Item Not Received)
    Route::get('/inquiries/{id}', [EbayController::class, 'getInquiries'])->name('ebay.inquiries.index')->middleware('permission:view orders');
    Route::post('/inquiries/{id}/{inquiryId}/shipment-info', [EbayController::class, 'provideInquiryShipmentInfo'])->name('ebay.inquiries.shipment-info')->middleware('permission:edit orders');
    Route::post('/inquiries/{id}/{inquiryId}/refund', [EbayController::class, 'issueInquiryRefund'])->name('ebay.inquiries.refund')->middleware('permission:edit orders');

    // Notification subscriptions
    Route::post('/notifications/{id}/subscribe-complete', [EbayController::class, 'subscribeToCompleteOrderEvents'])->name('ebay.notifications.subscribe-complete')->middleware('permission:edit sales-channels');
    Route::post('/notifications/{id}/subscribe-returns', [EbayController::class, 'subscribeToReturnEvents'])->name('ebay.notifications.subscribe-returns')->middleware('permission:edit sales-channels');

    // Local order views
    Route::get('/orders/{id}/with-returns', [EbayController::class, 'getOrdersWithReturns'])->name('ebay.orders.with-returns')->middleware('permission:view orders');
    Route::get('/orders/{id}/with-cancellations', [EbayController::class, 'getOrdersWithCancellations'])->name('ebay.orders.with-cancellations')->middleware('permission:view orders');
    Route::get('/orders/{id}/refunded', [EbayController::class, 'getRefundedOrders'])->name('ebay.orders.refunded')->middleware('permission:view orders');
});

/*
|--------------------------------------------------------------------------
| Local Order Management Routes (Non-eBay Orders)
|--------------------------------------------------------------------------
*/
Route::prefix('orders')->middleware(['auth:sanctum', 'throttle:api', 'permission:edit orders'])->group(function () {
    Route::post('/{orderId}/cancel', [EbayController::class, 'cancelLocalOrder'])->name('orders.cancel');
    Route::post('/{orderId}/refund', [EbayController::class, 'refundLocalOrder'])->name('orders.refund');
    Route::post('/{orderId}/refund/partial', [EbayController::class, 'partialRefundLocalOrder'])->name('orders.refund.partial');
});

/*
|--------------------------------------------------------------------------
| Inventory Sync Routes
|--------------------------------------------------------------------------
|
| Buffered inventory synchronization to eBay stores.
| Manages visible quantities with overselling protection.
|
*/
Route::prefix('inventory-sync')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Status and preview
    Route::get('/status/{product}', [InventorySyncController::class, 'status'])->name('inventory-sync.status')->middleware('permission:view products');
    Route::get('/preview/{product}', [InventorySyncController::class, 'preview'])->name('inventory-sync.preview')->middleware('permission:view products');

    // Sync operations
    Route::post('/sync/{product}', [InventorySyncController::class, 'sync'])->name('inventory-sync.sync')->middleware('permission:edit products');
    Route::post('/queue/{product}', [InventorySyncController::class, 'queue'])->name('inventory-sync.queue')->middleware('permission:edit products');
    Route::post('/force/{product}', [InventorySyncController::class, 'forceSync'])->name('inventory-sync.force')->middleware('permission:edit products');
    Route::post('/channel/{channel}', [InventorySyncController::class, 'syncChannel'])->name('inventory-sync.channel')->middleware('permission:edit sales-channels');

    // Logs and stats
    Route::get('/logs', [InventorySyncController::class, 'logs'])->name('inventory-sync.logs')->middleware('permission:view products');
    Route::get('/stats', [InventorySyncController::class, 'stats'])->name('inventory-sync.stats')->middleware('permission:view products');

    // Settings
    Route::post('/settings/{product}/{channel}', [InventorySyncController::class, 'updateSettings'])->name('inventory-sync.settings')->middleware('permission:edit products');
});
