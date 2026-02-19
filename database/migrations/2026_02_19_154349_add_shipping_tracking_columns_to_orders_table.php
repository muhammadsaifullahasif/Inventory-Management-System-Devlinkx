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
            $table->unsignedBigInteger('shipping_id')->nullable()->after('shipping_carrier');
            $table->string('tracking_url')->nullable()->after('tracking_number');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('tracking_last_checked_at')->nullable()->after('delivered_at');

            $table->foreign('shipping_id')->references('id')->on('shippings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_id']);
            $table->dropColumn(['shipping_id', 'tracking_url', 'delivered_at', 'tracking_last_checked_at']);
        });
    }
};
