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
            // Extended eBay order ID
            $table->string('ebay_extended_order_id')->nullable()->after('ebay_order_id');

            // Buyer additional fields
            $table->string('buyer_first_name')->nullable()->after('buyer_name');
            $table->string('buyer_last_name')->nullable()->after('buyer_first_name');

            // Shipping additional fields
            $table->string('shipping_country_name')->nullable()->after('shipping_country');

            // eBay checkout message
            $table->text('buyer_checkout_message')->nullable()->after('ebay_payment_status');

            // Cancel status
            $table->string('cancel_status')->nullable()->after('ebay_payment_status');

            // Notification tracking
            $table->string('notification_type')->nullable()->after('ebay_raw_data');
            $table->timestamp('notification_received_at')->nullable()->after('notification_type');

            // Index for extended order ID
            $table->index('ebay_extended_order_id');
        });

        // Add additional fields to order_items table
        Schema::table('order_items', function (Blueprint $table) {
            // Additional eBay item fields
            $table->decimal('actual_shipping_cost', 10, 2)->default(0)->after('total_price');
            $table->decimal('actual_handling_cost', 10, 2)->default(0)->after('actual_shipping_cost');
            $table->decimal('final_value_fee', 10, 2)->default(0)->after('actual_handling_cost');
            $table->string('listing_type')->nullable()->after('final_value_fee');
            $table->string('condition_id')->nullable()->after('listing_type');
            $table->string('condition_display_name')->nullable()->after('condition_id');
            $table->string('site')->nullable()->after('condition_display_name');
            $table->string('shipping_service')->nullable()->after('site');
            $table->text('buyer_checkout_message')->nullable()->after('shipping_service');
            $table->timestamp('item_paid_time')->nullable()->after('buyer_checkout_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ebay_extended_order_id']);
            $table->dropColumn([
                'ebay_extended_order_id',
                'buyer_first_name',
                'buyer_last_name',
                'shipping_country_name',
                'buyer_checkout_message',
                'cancel_status',
                'notification_type',
                'notification_received_at',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'actual_shipping_cost',
                'actual_handling_cost',
                'final_value_fee',
                'listing_type',
                'condition_id',
                'condition_display_name',
                'site',
                'shipping_service',
                'buyer_checkout_message',
                'item_paid_time',
            ]);
        });
    }
};
