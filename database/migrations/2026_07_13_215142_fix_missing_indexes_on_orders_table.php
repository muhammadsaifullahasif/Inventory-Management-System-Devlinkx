<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // orders_cancel_status_index was found corrupted (Index_type=Corrupted in SHOW INDEX),
        // and every other secondary index/unique on this table was missing entirely — same
        // class of metadata-stripping incident as the auto_increment issue fixed 2026-07-13.
        // Data itself passed CHECK TABLE, so this only rebuilds index metadata.
        DB::statement('ALTER TABLE orders DROP INDEX orders_cancel_status_index');

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('order_number');
            $table->unique('ebay_order_id');
            $table->index('sales_channel_id');
            $table->index('order_status');
            $table->index('payment_status');
            $table->index('order_date');
            $table->index('created_at');
            $table->index('cancel_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->dropUnique(['ebay_order_id']);
            $table->dropIndex(['sales_channel_id']);
            $table->dropIndex(['order_status']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['order_date']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['cancel_status']);
        });
    }
};
