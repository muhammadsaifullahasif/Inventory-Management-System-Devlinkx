<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('product_metas')
            ->where('meta_key', 'ebay_item_id')
            ->update(['meta_key' => 'channel_item_id']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('product_metas')
            ->where('meta_key', 'channel_item_id')
            ->update(['meta_key' => 'ebay_item_id']);
    }
};
