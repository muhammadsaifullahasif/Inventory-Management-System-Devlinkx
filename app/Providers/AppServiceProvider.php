<?php

namespace App\Providers;

use App\Models\BackupSetting;
use App\Models\ProductStock;
use App\Observers\ProductStockObserver;
use App\Services\Ebay\EbayApiClient;
use App\Services\Ebay\EbayNotificationService;
use App\Services\Ebay\EbayOrderService;
use App\Services\Ebay\EbayService;
use App\Services\Ebay\EbayXmlBuilder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EbayApiClient::class);
        $this->app->singleton(EbayXmlBuilder::class);
        $this->app->singleton(EbayService::class);
        $this->app->singleton(EbayOrderService::class);
        $this->app->singleton(EbayNotificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });

        // Register ProductStock observer for auto-syncing bundle stock
        ProductStock::observe(ProductStockObserver::class);

        // eBay can burst notifications; keep this generous and IP-keyed
        // so retried deliveries from eBay's servers don't get starved.
        RateLimiter::for('ebay-webhook', function ($request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        // Standard limiter for internal/admin-facing API endpoints
        // (returns, cancellations, refunds, inventory-sync, etc).
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // /up health check: fail (500) if DB or queue connection is unreachable
        Event::listen(DiagnosingHealth::class, function () {
            DB::connection()->getPdo();
            Queue::connection()->size();
        });

        // spatie/laravel-backup: never let a failed/stale backup pass silently.
        // Same pattern as $onScheduleFailure in routes/console.php.
        $onBackupFailure = function (string $reason) {
            return function () use ($reason) {
                Log::critical("Backup failure: {$reason}");

                if (config('sentry.dsn')) {
                    \Sentry\captureMessage("Backup failure: {$reason}", \Sentry\Severity::fatal());
                }
            };
        };

        Event::listen(BackupHasFailed::class, $onBackupFailure('backup:run failed'));
        Event::listen(CleanupHasFailed::class, $onBackupFailure('backup:clean failed'));
        Event::listen(UnhealthyBackupWasFound::class, $onBackupFailure('backup:monitor found an unhealthy/stale backup'));

        Event::listen(HealthyBackupWasFound::class, function () {
            Log::info('backup:monitor: backup is healthy');
        });

        // Retention + notification email set on the Backup Settings page
        // (BackupSetting row) override config/backup.php at runtime.
        // BackupSetting::current() falls back to these same defaults if the
        // table isn't migrated yet, so this is a no-op pre-migration.
        $backupSettings = BackupSetting::current();

        config([
            'backup.cleanup.default_strategy.keep_daily_backups_for_days' => $backupSettings->keep_daily_backups_for_days,
            'backup.cleanup.default_strategy.keep_weekly_backups_for_weeks' => $backupSettings->keep_weekly_backups_for_weeks,
            'backup.cleanup.default_strategy.keep_monthly_backups_for_months' => $backupSettings->keep_monthly_backups_for_months,
            'backup.notifications.mail.to' => $backupSettings->notification_email ?: config('backup.notifications.mail.to'),
        ]);
    }
}
