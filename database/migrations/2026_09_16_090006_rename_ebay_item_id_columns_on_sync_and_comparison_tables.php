<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_sync_logs', function (Blueprint $table) {
            $table->renameColumn('ebay_item_id', 'channel_item_id');
        });

        Schema::table('product_price_comparisons', function (Blueprint $table) {
            $table->renameColumn('ebay_item_id', 'channel_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_sync_logs', function (Blueprint $table) {
            $table->renameColumn('channel_item_id', 'ebay_item_id');
        });

        Schema::table('product_price_comparisons', function (Blueprint $table) {
            $table->renameColumn('channel_item_id', 'ebay_item_id');
        });
    }
};
