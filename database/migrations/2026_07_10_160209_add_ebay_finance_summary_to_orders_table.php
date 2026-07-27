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
            $table->decimal('ebay_transaction_fee', 10, 2)->nullable();
            $table->decimal('ebay_shipping_label_cost', 10, 2)->nullable();
            $table->decimal('ebay_ad_fee', 10, 2)->nullable();
            $table->decimal('ebay_other_fees', 10, 2)->nullable();
            $table->decimal('ebay_net_earnings', 10, 2)->nullable();
            $table->timestamp('ebay_financials_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ebay_transaction_fee',
                'ebay_shipping_label_cost',
                'ebay_ad_fee',
                'ebay_other_fees',
                'ebay_net_earnings',
                'ebay_financials_synced_at',
            ]);
        });
    }
};
