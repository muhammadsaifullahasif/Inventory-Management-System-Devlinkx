<?php

namespace App\Providers;

use App\Services\Ebay\EbayApiClient;
use App\Services\Ebay\EbayNotificationService;
use App\Services\Ebay\EbayOrderService;
use App\Services\Ebay\EbayService;
use App\Services\Ebay\EbayXmlBuilder;
use Illuminate\Support\Facades\Gate;
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
    }
}
