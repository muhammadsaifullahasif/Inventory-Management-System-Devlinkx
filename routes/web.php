<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\BillController;
use App\Http\Controllers\EbayController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SalesChannelController;
use App\Http\Controllers\ChartOfAccountController;

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

Route::get('/run-chart-of-accounts-seeder', function() {
    Artisan::call('db:seed', [
        '--class' => 'ChartOfAccountsSeeder'
    ]);

    return response()->json([
        'message' => 'ChartOfAccountsSeeder executed',
        'output' => Artisan::output()
    ]);
});

Route::get('/run-accounting-permissions-seeder', function() {
    Artisan::class('db:seed', [
        '--class' => 'AccountingPermissionsSeeder'
    ]);

    return response()->json([
        'message' => 'AccountingPermissionsSeeder executed',
        'output' => Artisan::output()
    ]);
});

Auth::routes();

// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Dashboard Widget Settings
    Route::get('/dashboard/widgets', [DashboardController::class, 'widgetSettings'])->name('dashboard.widgets');
    Route::post('/dashboard/widgets', [DashboardController::class, 'updateWidgetSettings'])->name('dashboard.widgets.update');
    Route::post('/dashboard/widgets/toggle', [DashboardController::class, 'toggleWidget'])->name('dashboard.widgets.toggle');
    Route::post('/dashboard/widgets/reset', [DashboardController::class, 'resetWidgets'])->name('dashboard.widgets.reset');

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
    Route::get('/products/print-barcode/{id}', [ProductController::class, 'printBarcode'])->name('products.print-barcode');
    Route::get('/products/barcode/print/{id}', [ProductController::class, 'printBarcodeView'])->name('products.barcode.print');
    Route::get('/products/barcode/bulk', [ProductController::class, 'bulkPrintBarcodeForm'])->name('products.barcode.bulk-form');
    Route::post('/products/barcode/bulk', [ProductController::class, 'bulkPrintBarcode'])->name('products.barcode.bulk-print');
    Route::post('/products/{id}/update-stock', [ProductController::class, 'updateStock'])->name('products.update-stock');

    // Purchases
    Route::resource('/purchases', PurchaseController::class);
    Route::post('/purchases/{id}/receive-stock', [PurchaseController::class, 'purchase_receive_stock'])->name('purchases.receive.stock');

    // Sales Channel
    Route::resource('/sales-channels', SalesChannelController::class);
    Route::get('/ebay/callback', [SalesChannelController::class, 'ebay_callback'])->name('ebay.callback');

    // eBay Notification Management
    Route::get('/ebay/notifications/status/{id}', [SalesChannelController::class, 'checkNotificationStatus'])->name('ebay.notifications.status');
    Route::post('/ebay/notifications/resubscribe/{id}', [SalesChannelController::class, 'resubscribeNotifications'])->name('ebay.notifications.resubscribe');
    Route::post('/ebay/notifications/disable/{id}', [SalesChannelController::class, 'disableNotifications'])->name('ebay.notifications.disable');

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

    // eBay Sync Listings (import new products only)
    Route::get('/ebay/listings/sync/{id}', [EbayController::class, 'syncListings'])->name('ebay.listings.sync');

    // eBay Orders
    Route::get('/ebay/orders/sync/{id}', [EbayController::class, 'syncOrders'])->name('ebay.orders.sync');
    Route::get('/ebay/orders/sync-queue/{id}', [EbayController::class, 'syncOrdersQueue'])->name('ebay.orders.sync.queue');
    Route::get('/ebay/orders/{id}', [EbayController::class, 'getOrders'])->name('ebay.orders.list');

    Route::get('/ebay/orders/import/{id}', [EbayController::class, 'getEbayOrders'])->name('ebay.orders.import');

    // Orders Management
    Route::resource('/orders', OrderController::class);
    Route::get('/orders/ebay/{ebayOrderId}', [OrderController::class, 'getByEbayOrderId'])->name('orders.ebay');
    Route::post('/orders/{id}/ship', [OrderController::class, 'markAsShipped'])->name('orders.ship');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders-statistics', [OrderController::class, 'statistics'])->name('orders.statistics');


    // Chart of Accounts
    Route::resource('chart-of-accounts', ChartOfAccountController::class);
    Route::get('chart-of-accounts-by-group/{group}', [ChartOfAccountController::class, 'getByGroup'])->name('chart-of-accounts.by-group');
    Route::get('chart-of-accounts-expense', [ChartOfAccountController::class, 'getExpenseAccounts'])->name('chart-of-accounts.expense');
    Route::get('chart-of-accounts-bank-cash', [ChartOfAccountController::class, 'getBankCashAccounts'])->name('chart-of-accounts.bank-cash');
    Route::post('chart-of-accounts-quick-store', [ChartOfAccountController::class, 'quickStore'])->name('chart-of-accounts.quick-store');

    // Bills
    Route::resource('bills', BillController::class);
    Route::post('bills/{bill}/post', [BillController::class, 'post'])->name('bills.post');
    Route::get('bills-expense-accounts/{group}', [BillController::class, 'getExpenseAccountsByGroup'])
        ->name('bills.expense-accounts');

    // Payments
    Route::resource('payments', PaymentController::class)->except(['edit', 'update']);
    Route::get('payments-bill-details/{bill}', [PaymentController::class, 'getBillDetails'])
        ->name('payments.bill-details');
});


