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
        Schema::rename('ebay_finance_transactions', 'sales_channel_finance_transactions');

        Schema::table('sales_channel_finance_transactions', function (Blueprint $table) {
            $table->renameColumn('ebay_transaction_id', 'channel_transaction_id');
            $table->renameColumn('ebay_order_id', 'channel_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_channel_finance_transactions', function (Blueprint $table) {
            $table->renameColumn('channel_transaction_id', 'ebay_transaction_id');
            $table->renameColumn('channel_order_id', 'ebay_order_id');
        });

        Schema::rename('sales_channel_finance_transactions', 'ebay_finance_transactions');
    }
};
