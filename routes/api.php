<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EbayController;

Route::post('/ebay/orders/webhook/{id}', [EbayController::class, 'handleEbayOrderWebhook'])->name('ebay.orders.webhook');