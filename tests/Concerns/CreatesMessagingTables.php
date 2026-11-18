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
        });

        foreach (glob(base_path('database/migrations/2026_11_18_00000*_*.php')) as $file) {
            (require $file)->up();
        }
    }
}
