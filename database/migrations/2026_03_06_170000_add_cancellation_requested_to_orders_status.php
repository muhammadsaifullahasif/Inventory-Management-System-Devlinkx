<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'cancellation_requested' and 'awaiting_payment' to the order_status enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'cancellation_requested', 'awaiting_payment', 'ready_for_pickup') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        // First update any rows with new values to 'pending'
        DB::table('orders')->whereIn('order_status', ['cancellation_requested', 'awaiting_payment', 'ready_for_pickup'])->update(['order_status' => 'pending']);

        DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending'");
    }
};
