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
