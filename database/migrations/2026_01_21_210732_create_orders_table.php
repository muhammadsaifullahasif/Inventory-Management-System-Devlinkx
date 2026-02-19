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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('sales_channel_id');
            $table->string('ebay_order_id')->unique()->nullable();

            // Buyer Information
            $table->string('buyer_username')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_phone')->nullable();

            // Shipping Address
            $table->string('shipping_name')->nullable();
            $table->text('shipping_address_line1')->nullable();
            $table->text('shipping_address_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->string('shipping_country')->nullable();

            // Order Totals
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            // Status
            $table->enum('order_status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'refunded', 'failed'])->default('pending');
            $table->enum('fulfillment_status', ['unfulfilled', 'partially_fulfilled', 'fulfilled'])->default('unfulfilled');

            // Shipping Info
            $table->string('shipping_carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();

            // eBay specific
            $table->string('ebay_order_status')->nullable();
            $table->string('ebay_payment_status')->nullable();
            $table->json('ebay_raw_data')->nullable();

            // Timestamps
            $table->timestamp('order_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('sales_channel_id');
            $table->index('order_status');
            $table->index('payment_status');
            $table->index('order_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('orders');
        Schema::enableForeignKeyConstraints();
    }
};
