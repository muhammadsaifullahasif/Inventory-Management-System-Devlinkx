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
        Schema::table('sales_channel_product', function (Blueprint $table) {
            // Listing status: active, draft, ended, pending, error
            $table->string('listing_status')->default('pending')->after('external_listing_id');
            // Store any listing error message
            $table->text('listing_error')->nullable()->after('listing_status');
            // eBay specific: store the listing format (FixedPriceItem, etc.)
            $table->string('listing_format')->nullable()->after('listing_error');
            // Last sync timestamp
            $table->timestamp('last_synced_at')->nullable()->after('listing_format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_channel_product', function (Blueprint $table) {
            $table->dropColumn(['listing_status', 'listing_error', 'listing_format', 'last_synced_at']);
        });
    }
};
