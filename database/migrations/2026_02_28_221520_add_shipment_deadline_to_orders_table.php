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
        Schema::table('orders', function (Blueprint $table) {
            // Shipment deadline - the date by which the order must be shipped
            // Calculated from order creation/payment time + seller's handling time (DispatchTimeMax)
            $table->timestamp('shipment_deadline')->nullable()->after('shipped_at');

            // Handling time in business days (from eBay DispatchTimeMax)
            $table->unsignedSmallInteger('handling_time_days')->nullable()->after('shipment_deadline');

            // Index for filtering orders by shipment deadline
            $table->index('shipment_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipment_deadline']);
            $table->dropColumn(['shipment_deadline', 'handling_time_days']);
        });
    }
};
