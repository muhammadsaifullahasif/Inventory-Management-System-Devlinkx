<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // These are set client-side as plain JSON by partials/column-toggle.blade.php,
        // not Laravel-encrypted - exclude so the server can read them back.
        $middleware->encryptCookies(except: [
            'category_columns',
            'permission_columns',
            'brand_columns',
            'warehouse_columns',
            'sales_channel_columns',
            'user_columns',
            'orders_columns',
            'role_columns',
            'rack_columns',
            'out_of_stock_columns',
            'shipping_checklist_columns',
            'shipping_columns',
            'slow_moving_stock_columns',
            'supplier_columns',
            'products_columns',
            'purchase_columns',
            'frequently_order_stock_columns',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Capture context for every logged exception
        $exceptions->context(function (Throwable $e) {
            return [
                'executed_from_file' => $e->getFile(),
                'executed_at_line' => $e->getLine(),
            ];
        });

        Integration::handles($exceptions);
    })->create();
