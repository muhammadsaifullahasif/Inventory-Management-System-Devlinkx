<?php

namespace App\Providers;

use App\Models\BackupSetting;
use App\Models\ProductStock;
use App\Models\ScheduleTaskRun;
use App\Observers\AuditObserver;
use App\Observers\ProductStockObserver;
use App\Services\Ebay\EbayApiClient;
use App\Services\Ebay\EbayNotificationService;
use App\Services\Ebay\EbayOrderService;
use App\Services\Ebay\EbayService;
use App\Services\Ebay\EbayXmlBuilder;
use App\Support\AuditContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Queue\Events\JobProcessing;
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
        $this->app->singleton(AuditContext::class);
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

        // Audit trail: generic create/update/delete tracking for every model
        // listed in config/audit.php, without touching each model individually.
        foreach (config('audit.audited_models', []) as $auditedModel) {
            $auditedModel::observe(AuditObserver::class);
        }

        // Audit trail: queue jobs have no HTTP request, so give them their
        // own actor context ("queue", + job class) instead of leaking
        // whatever the last processed request/job happened to set.
        Queue::before(function (JobProcessing $event) {
            $context = app(AuditContext::class);
            $context->reset();
            $context->actorType = 'queue';
            $context->context = [
                'job' => $event->job->resolveName(),
                'job_id' => $event->job->getJobId(),
            ];
        });

        // Audit trail: login/logout/failed-login events, captured with
        // whatever ip/user-agent CaptureAuditContext already resolved for
        // this request.
        Event::listen(Login::class, function (Login $event) {
            $context = app(AuditContext::class);
            $context->actorType = 'user';
            $context->actorId = $event->user->id;
            $context->actorLabel = $event->user->name ?? $event->user->email;

            app(\App\Services\AuditLogger::class)->log('login', $event->user);
        });

        Event::listen(Logout::class, function (Logout $event) {
            if (! $event->user) {
                return;
            }

            $context = app(AuditContext::class);
            $context->actorType = 'user';
            $context->actorId = $event->user->id;
            $context->actorLabel = $event->user->name ?? $event->user->email;

            app(\App\Services\AuditLogger::class)->log('logout', $event->user);
        });

        Event::listen(Failed::class, function (Failed $event) {
            app(\App\Services\AuditLogger::class)->log(
                'login_failed',
                $event->user,
                [],
                [],
                ['email' => $event->credentials['email'] ?? null]
            );
        });

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

        // Record every scheduled task run (fires for all Schedule::command()/job()
        // entries automatically) so System Health can flag overdue/missed tasks.
        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) {
            ScheduleTaskRun::updateOrCreate(
                ['command' => $event->task->getSummaryForDisplay()],
                ['status' => 'success', 'last_ran_at' => now(), 'runtime_ms' => (int) round($event->runtime * 1000)]
            );
        });

        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event) {
            ScheduleTaskRun::updateOrCreate(
                ['command' => $event->task->getSummaryForDisplay()],
                ['status' => 'failed', 'last_ran_at' => now()]
            );
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
