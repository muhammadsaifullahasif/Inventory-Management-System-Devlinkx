<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\EbayController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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

// Handle both GET (challenge) and POST (notifications) for eBay webhooks
Route::match(['get', 'post'], '/ebay/webhook/{id}', [EbayController::class, 'handleEbayOrderWebhook'])
    ->name('ebay.webhook');

// Legacy route (for backwards compatibility)
Route::post('/ebay/orders/webhook/{id}', [EbayController::class, 'handleEbayOrderWebhook'])
    ->name('ebay.orders.webhook');

// Test endpoint to verify webhook is reachable
Route::get('/ebay/webhook-test/{id}', function (Request $request, string $id) {
    Log::channel('ebay')->info('Webhook test endpoint hit', [
        'sales_channel_id' => $id,
        'timestamp' => now()->toIso8601String(),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Webhook endpoint is reachable',
        'sales_channel_id' => $id,
        'timestamp' => now()->toIso8601String(),
        'webhook_url' => url("/api/ebay/webhook/{$id}"),
    ]);
})->name('ebay.webhook.test');

/*
|--------------------------------------------------------------------------
| eBay Returns Management Routes
|--------------------------------------------------------------------------
*/
Route::prefix('ebay')->group(function () {
    // Returns
    Route::get('/returns/{id}', [EbayController::class, 'getReturns'])->name('ebay.returns.index');
    Route::get('/returns/{id}/{returnId}', [EbayController::class, 'getReturnDetails'])->name('ebay.returns.show');
    Route::post('/returns/{id}/{returnId}/approve', [EbayController::class, 'approveReturn'])->name('ebay.returns.approve');
    Route::post('/returns/{id}/{returnId}/decline', [EbayController::class, 'declineReturn'])->name('ebay.returns.decline');
    Route::post('/returns/{id}/{returnId}/shipping-label', [EbayController::class, 'provideReturnShippingLabel'])->name('ebay.returns.shipping-label');
    Route::post('/returns/{id}/{returnId}/mark-received', [EbayController::class, 'markReturnReceived'])->name('ebay.returns.mark-received');
    Route::post('/returns/{id}/{returnId}/refund', [EbayController::class, 'issueReturnRefund'])->name('ebay.returns.refund');
    Route::post('/returns/{id}/{returnId}/close', [EbayController::class, 'closeReturn'])->name('ebay.returns.close');

    // Cancellations
    Route::get('/cancellations/{id}', [EbayController::class, 'getCancellations'])->name('ebay.cancellations.index');
    Route::post('/cancellations/{id}/{orderId}/approve', [EbayController::class, 'approveCancellation'])->name('ebay.cancellations.approve');
    Route::post('/cancellations/{id}/{orderId}/reject', [EbayController::class, 'rejectCancellation'])->name('ebay.cancellations.reject');
    Route::post('/cancellations/{id}/{orderId}/create', [EbayController::class, 'createCancellation'])->name('ebay.cancellations.create');

    // Refunds
    Route::post('/refunds/{id}/{orderId}', [EbayController::class, 'issueRefund'])->name('ebay.refunds.issue');
    Route::post('/refunds/{id}/{orderId}/partial', [EbayController::class, 'issuePartialRefund'])->name('ebay.refunds.partial');

    // Inquiries (INR - Item Not Received)
    Route::get('/inquiries/{id}', [EbayController::class, 'getInquiries'])->name('ebay.inquiries.index');
    Route::post('/inquiries/{id}/{inquiryId}/shipment-info', [EbayController::class, 'provideInquiryShipmentInfo'])->name('ebay.inquiries.shipment-info');
    Route::post('/inquiries/{id}/{inquiryId}/refund', [EbayController::class, 'issueInquiryRefund'])->name('ebay.inquiries.refund');

    // Notification subscriptions
    Route::post('/notifications/{id}/subscribe-complete', [EbayController::class, 'subscribeToCompleteOrderEvents'])->name('ebay.notifications.subscribe-complete');
    Route::post('/notifications/{id}/subscribe-returns', [EbayController::class, 'subscribeToReturnEvents'])->name('ebay.notifications.subscribe-returns');

    // Local order views
    Route::get('/orders/{id}/with-returns', [EbayController::class, 'getOrdersWithReturns'])->name('ebay.orders.with-returns');
    Route::get('/orders/{id}/with-cancellations', [EbayController::class, 'getOrdersWithCancellations'])->name('ebay.orders.with-cancellations');
    Route::get('/orders/{id}/refunded', [EbayController::class, 'getRefundedOrders'])->name('ebay.orders.refunded');
});

/*
|--------------------------------------------------------------------------
| Local Order Management Routes (Non-eBay Orders)
|--------------------------------------------------------------------------
*/
Route::prefix('orders')->group(function () {
    Route::post('/{orderId}/cancel', [EbayController::class, 'cancelLocalOrder'])->name('orders.cancel');
    Route::post('/{orderId}/refund', [EbayController::class, 'refundLocalOrder'])->name('orders.refund');
    Route::post('/{orderId}/refund/partial', [EbayController::class, 'partialRefundLocalOrder'])->name('orders.refund.partial');
});
