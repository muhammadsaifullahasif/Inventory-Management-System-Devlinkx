<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_channels', function (Blueprint $table) {
            // Add a JSON column to store multiple eBay user IDs
            // eBay uses different RecipientUserIDs for different notification types
            $table->json('ebay_user_ids')->nullable()->after('ebay_user_id');
        });

        // Migrate existing ebay_user_id to ebay_user_ids array
        DB::statement("
            UPDATE sales_channels
            SET ebay_user_ids = JSON_ARRAY(ebay_user_id)
            WHERE ebay_user_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_channels', function (Blueprint $table) {
            $table->dropColumn('ebay_user_ids');
        });
    }
};
