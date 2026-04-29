<?php

use App\Jobs\UpdateEbayOrderStatusJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Check delivery status for shipped orders every 2 hours
Schedule::command('orders:check-delivery-status --limit=100')
    ->everyTwoHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/delivery-status.log'));

// Update eBay order statuses (cancel/refund/return) every 12 hours
Schedule::job(new UpdateEbayOrderStatusJob(90))
    ->twiceDaily(6, 18) // Run at 6 AM and 6 PM
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ebay-order-status.log'));

// Refresh eBay tokens every 30 minutes (tokens expire after ~2 hours)
Schedule::command('tokens:refresh-ebay')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/token-refresh.log'));

// Refresh shipping carrier tokens every 30 minutes
Schedule::command('tokens:refresh-shipping')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/token-refresh.log'));

// Sync eBay orders every 15 minutes (backup for missed notifications)
// Fetches today's orders by default
Schedule::command('ebay:sync-orders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ebay-order-sync.log'));

Schedule::command('queue:release-stale')->everyFiveMintues();
