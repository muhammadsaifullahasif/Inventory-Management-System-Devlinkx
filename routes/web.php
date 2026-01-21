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

Route::get('/run-queue', function() {
    if (!app()->environment('local')) {
        abort(403, 'Queue worker can only be run in the local environment.');
    }

    // Get pending job count
    $pendingJobs = DB::table('jobs')->where('queue', 'ebay-imports')->count();

    if ($pendingJobs === 0) {
        return response()->json([
            'message' => 'No pending jobs in ebay-imports queue.',
            'pending_jobs' => 0,
        ]);
    }

    // Process ONE job at a time to avoid timeout
    Artisan::call('queue:work', [
        '--queue' => 'ebay-imports',
        '--once' => true,
        '--timeout' => 300,
    ]);

    $remainingJobs = DB::table('jobs')->where('queue', 'ebay-imports')->count();

    return response()->json([
        'message' => 'Processed 1 job.',
        'remaining_jobs' => $remainingJobs,
        'output' => Artisan::output()
    ]);
});

// Process all queue jobs (use with caution - may timeout)
Route::get('/run-queue-all', function() {
    if (!app()->environment('local')) {
        abort(403, 'Queue worker can only be run in the local environment.');
    }

    set_time_limit(600); // 10 minutes max

    Artisan::call('queue:work', [
        '--queue' => 'ebay-imports',
        '--stop-when-empty' => true,
        '--timeout' => 300,
    ]);

    return response()->json([
        'message' => 'All queue jobs processed.',
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

    // eBay Get Single Item Details (Trading API - GetItem)
    Route::get('/ebay/item/{id}/{itemId}', [EbayController::class, 'getItemDetails'])->name('ebay.item.details');

    // eBay Update Listing (Trading API - ReviseItem)
    // Route::put('/ebay/listing/{id}/{itemId}', [EbayController::class, 'updateListing'])->name('ebay.listing.update');
    // Route::patch('/ebay/listing/{id}/{itemId}/quantity', [EbayController::class, 'updateListingQuantity'])->name('ebay.listing.update.quantity');
    // Route::patch('/ebay/listing/{id}/{itemId}/price', [EbayController::class, 'updateListingPrice'])->name('ebay.listing.update.price');

    // eBay Import Status
    Route::get('/ebay/import-status/{importLogId}', [EbayController::class, 'getImportStatus'])->name('ebay.import.status');
    Route::get('/ebay/import-logs', [EbayController::class, 'listImportLogs'])->name('ebay.import.logs');
    Route::get('/ebay/import-logs/latest/{salesChannelId}', [EbayController::class, 'getLatestImportLog'])->name('ebay.import.latest');

    // eBay Orders
    Route::get('/ebay/orders/sync/{id}', [EbayController::class, 'syncOrders'])->name('ebay.orders.sync');
    Route::get('/ebay/orders/sync-queue/{id}', [EbayController::class, 'syncOrdersQueue'])->name('ebay.orders.sync.queue');
    Route::get('/ebay/orders/{id}', [EbayController::class, 'getOrders'])->name('ebay.orders.list');

    Route::get('/ebay/orders/import/{id}', [EbayController::class, 'getEbayOrders'])->name('ebay.orders.import');
});
