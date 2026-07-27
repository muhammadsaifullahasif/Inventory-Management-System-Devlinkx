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
            $table->string('listing_url')->nullable()->after('sales_channel_id');
            $table->string('external_listing_id')->nullable()->after('listing_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_channel_product', function (Blueprint $table) {
            $table->dropColumn(['listing_url', 'external_listing_id', 'created_at', 'updated_at']);
        });
    }
};
