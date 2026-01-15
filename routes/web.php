<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\EbayController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SalesChannelController;

Route::get('/run-migrations', function() {
    if (!app()->environment('local')) {
        abort(403, 'Migrations can only be run in the local environment.');
    }

    Artisan::call('migrate', ['--force' => true]);

    return response()->json([
        'message' => 'Migrations ran successfully.',
        'output' => Artisan::output()
    ]);
});

Route::get('/rollback-migrations', function() {
    // Allow only local or staging environments to run this route
    if (!app()->environment(['local', 'staging'])) {
        abort(403, 'Migrations can only be rolled back in the local or staging environments.');
    }

    Artisan::call('migrate:rollback', ['--force' => true]);

    return response()->json([
        'message' => 'Migrations rolled back successfully.',
        'output' => Artisan::output()
    ]);
});

Auth::routes();

// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('/users', UserController::class);
    Route::resource('/roles', RoleController::class);
    Route::resource('/permissions', PermissionController::class);

    // Categories
    Route::resource('/categories', CategoryController::class);

    // Brands
    Route::resource('/brands', BrandController::class);

    // Suppliers
    Route::resource('/suppliers', SupplierController::class);

    // Warehouses
    Route::resource('/warehouses', WarehouseController::class);

    // Racks
    Route::resource('/racks', RackController::class);
    Route::get('/warehouses/{warehouse}/racks', [RackController::class, 'getRacksByWarehouse'])->name('warehouses.racks');

    // Products
    Route::resource('/products', ProductController::class);
    Route::get('/products/search/{query}', [ProductController::class, 'search'])->name('products.search');

    // Purchases
    Route::resource('/purchases', PurchaseController::class);
    Route::post('/purchases/{id}/receive-stock', [PurchaseController::class, 'purchase_receive_stock'])->name('purchases.receive.stock');

    // Sales Channel
    Route::resource('/sales-channels', SalesChannelController::class);
    Route::get('/ebay/callback', [SalesChannelController::class, 'ebay_callback'])->name('ebay.callback');

    // eBay Seller Listings (Trading API - GetSellerList)
    Route::get('/ebay/listings-all/active/{id}', [EbayController::class, 'getAllActiveListings'])->name('ebay.listings-all.active');
});
