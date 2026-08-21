<?php

use App\Jobs\UpdateEbayOrderStatusJob;
use App\Models\BackupSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Logs + reports to Sentry on a non-zero exit code (a thrown exception inside
// the command is already reported separately via bootstrap/app.php).
$onScheduleFailure = function (string $task) {
    return function () use ($task) {
        Log::error("Scheduled task failed: {$task}");

        if (config('sentry.dsn')) {
            \Sentry\captureMessage("Scheduled task failed: {$task}");
        }
    };
};

// Check delivery status for shipped orders every 2 hours
Schedule::command('orders:check-delivery-status --limit=100')
    ->everyTwoHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/delivery-status.log'))
    ->onFailure($onScheduleFailure('orders:check-delivery-status'));

// Update eBay order statuses (cancel/refund/return) every 12 hours
Schedule::job(new UpdateEbayOrderStatusJob(90))
    ->twiceDaily(6, 18) // Run at 6 AM and 6 PM
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ebay-order-status.log'))
    ->onFailure($onScheduleFailure('UpdateEbayOrderStatusJob'));

// Refresh eBay tokens every 30 minutes (tokens expire after ~2 hours)
Schedule::command('tokens:refresh-ebay')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/token-refresh.log'))
    ->onFailure($onScheduleFailure('tokens:refresh-ebay'));

// Refresh shipping carrier tokens every 30 minutes
Schedule::command('tokens:refresh-shipping')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/token-refresh.log'))
    ->onFailure($onScheduleFailure('tokens:refresh-shipping'));

// Sync eBay orders every 15 minutes (backup for missed notifications)
// Fetches today's orders by default
Schedule::command('ebay:sync-orders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ebay-order-sync.log'))
    ->onFailure($onScheduleFailure('ebay:sync-orders'));

// Sync eBay finance data (fees, shipping labels, ad charges) hourly, 7-day lookback
Schedule::command('ebay:sync-finances')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ebay-finance-sync.log'))
    ->onFailure($onScheduleFailure('ebay:sync-finances'));

// Market Research: refresh eBay competitor price comparisons hourly (rotates through catalog, oldest-compared first)
Schedule::command('price-comparison:run --limit=50')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/price-comparison.log'))
    ->onFailure($onScheduleFailure('price-comparison:run'));

// Backup Settings page (backup_settings table) toggles all three below.
$backupScheduleEnabled = fn () => BackupSetting::current()->schedule_enabled;

// Full DB + storage/app + storage/logs backup, daily at 2 AM (low-traffic hour)
Schedule::command('backup:run')
    ->dailyAt('02:00')
    ->when($backupScheduleEnabled)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup-run.log'))
    ->onFailure($onScheduleFailure('backup:run'))
    // Only prune once the zip actually has yesterday's logs safely in it.
    ->onSuccess(fn () => Artisan::call('logs:prune-backed-up'));

// Enforce retention (default 7 daily / 4 weekly / 3 monthly — configurable
// on the Backup Settings page, config/backup.php cleanup.default_strategy)
Schedule::command('backup:clean')
    ->weeklyOn(1, '03:00') // Monday 3 AM, after the backup:run cycle
    ->when($backupScheduleEnabled)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup-clean.log'))
    ->onFailure($onScheduleFailure('backup:clean'));

// Health check: fires BackupHasFailed / UnhealthyBackupWasFound events on
// stale or missing backups, handled at critical level in AppServiceProvider.
Schedule::command('backup:monitor')
    ->dailyAt('02:30')
    ->when($backupScheduleEnabled)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup-monitor.log'))
    ->onFailure($onScheduleFailure('backup:monitor'));

Schedule::command('queue:release-stale')->everyFiveMinutes();

// Automatically runs the queue worker every minute cleanly via internal code routing
Schedule::command('queue:work database --queue=ebay-imports,inventory-sync,default --stop-when-empty --max-time=50 --timeout=1800')
    ->everyFiveMinutes();
