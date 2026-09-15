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
            $table->renameColumn('ebay_order_id', 'channel_order_id');
            $table->renameColumn('ebay_order_status', 'channel_order_status');
            $table->renameColumn('ebay_payment_status', 'channel_payment_status');
            $table->renameColumn('ebay_raw_data', 'channel_raw_data');
            $table->renameColumn('ebay_extended_order_id', 'channel_extended_order_id');
            $table->renameColumn('ebay_transaction_fee', 'channel_transaction_fee');
            $table->renameColumn('ebay_shipping_label_cost', 'channel_shipping_label_cost');
            $table->renameColumn('ebay_ad_fee', 'channel_ad_fee');
            $table->renameColumn('ebay_other_fees', 'channel_other_fees');
            $table->renameColumn('ebay_net_earnings', 'channel_net_earnings');
            $table->renameColumn('ebay_financials_synced_at', 'channel_financials_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('channel_order_id', 'ebay_order_id');
            $table->renameColumn('channel_order_status', 'ebay_order_status');
            $table->renameColumn('channel_payment_status', 'ebay_payment_status');
            $table->renameColumn('channel_raw_data', 'ebay_raw_data');
            $table->renameColumn('channel_extended_order_id', 'ebay_extended_order_id');
            $table->renameColumn('channel_transaction_fee', 'ebay_transaction_fee');
            $table->renameColumn('channel_shipping_label_cost', 'ebay_shipping_label_cost');
            $table->renameColumn('channel_ad_fee', 'ebay_ad_fee');
            $table->renameColumn('channel_other_fees', 'ebay_other_fees');
            $table->renameColumn('channel_net_earnings', 'ebay_net_earnings');
            $table->renameColumn('channel_financials_synced_at', 'ebay_financials_synced_at');
        });
    }
};
