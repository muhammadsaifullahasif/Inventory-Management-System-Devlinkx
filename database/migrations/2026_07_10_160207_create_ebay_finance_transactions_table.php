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
        Schema::create('ebay_finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('ebay_transaction_id')->unique();
            $table->string('ebay_order_id')->nullable()->index();
            $table->string('transaction_type'); // SALE, SHIPPING_LABEL, NON_SALE_CHARGE, REFUND, ...
            $table->string('fee_category')->nullable(); // sale | shipping_label | ad_fee | other

            $table->string('booking_entry')->nullable(); // CREDIT | DEBIT
            $table->decimal('amount', 12, 2);
            // Only populated for SALE transactions: eBay's own totalFeeAmount (final value fee + related marketplace fees).
            $table->decimal('total_fee_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            $table->string('payout_id')->nullable()->index();
            $table->timestamp('transaction_date');
            $table->json('raw_payload');

            $table->timestamps();

            $table->index(['sales_channel_id', 'transaction_date'], 'eft_channel_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_finance_transactions');
    }
};
