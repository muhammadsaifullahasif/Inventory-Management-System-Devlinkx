<?php

namespace App\Providers;

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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
    }
}
