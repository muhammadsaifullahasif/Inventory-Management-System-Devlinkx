<?php

namespace App\Providers;

use App\Events\StockUpdated;
use App\Listeners\CheckInventorySyncOnStockUpdate;
use App\Services\Inventory\InventorySyncService;
use App\Services\Inventory\VisibleStockCalculator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for inventory sync module.
 *
 * Registers:
 * - Service bindings (singleton for efficiency)
 * - Event listeners
 */
class InventorySyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Singleton: only one instance needed
        $this->app->singleton(VisibleStockCalculator::class);
        $this->app->singleton(InventorySyncService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register event listener
        Event::listen(
            StockUpdated::class,
            CheckInventorySyncOnStockUpdate::class
        );
    }
}
