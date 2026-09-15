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
        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('ebay_item_id', 'channel_item_id');
            $table->renameColumn('ebay_transaction_id', 'channel_transaction_id');
            $table->renameColumn('ebay_line_item_id', 'channel_line_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('channel_item_id', 'ebay_item_id');
            $table->renameColumn('channel_transaction_id', 'ebay_transaction_id');
            $table->renameColumn('channel_line_item_id', 'ebay_line_item_id');
        });
    }
};
