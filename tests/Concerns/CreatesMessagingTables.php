<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sets up an in-memory SQLite database for messaging tests: the real messaging migrations,
 * plus minimal stand-ins for the parent tables they point at (the app's full migration set
 * targets MySQL, so it is not run here).
 */
trait CreatesMessagingTables
{
    protected function createMessagingTables(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });

        Schema::create('sales_channels', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type')->default('ebay');
            $t->string('client_id')->nullable();
            $t->text('client_secret')->nullable();
            $t->text('refresh_token')->nullable();
            $t->string('external_account_id')->nullable();
            $t->text('external_account_ids')->nullable();
            $t->text('user_scopes')->nullable();
            $t->text('access_token')->nullable();
            $t->timestamp('access_token_expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('sku')->nullable();
            $t->decimal('price', 10, 2)->nullable();
            $t->string('product_image')->nullable();
        });

        // Product always eager-loads its meta rows.
        Schema::create('product_metas', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('meta_key')->nullable();
            $t->text('meta_value')->nullable();
        });

        Schema::create('sales_channel_product', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('sales_channel_id');
            $t->string('external_listing_id')->nullable();
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('sales_channel_id');
            $t->string('buyer_username')->nullable();
            $t->timestamp('order_date')->nullable();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('channel_item_id')->nullable();
            $t->string('sku')->nullable();
        });

        foreach (glob(base_path('database/migrations/2026_11_18_00000*_*.php')) as $file) {
            (require $file)->up();
        }
    }

    /**
     * Roles/permissions tables (spatie) and the notifications table, for tests that go through
     * the permission middleware or render the full page layout.
     */
    protected function createAuthTables(): void
    {
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }
}
