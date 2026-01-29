<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
