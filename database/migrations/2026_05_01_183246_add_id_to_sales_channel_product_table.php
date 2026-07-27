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
            // Add id as primary key before existing columns
            $table->id()->first();

            // Add unique constraint on composite key to prevent duplicates
            $table->unique(['product_id', 'sales_channel_id'], 'product_sales_channel_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_channel_product', function (Blueprint $table) {
            $table->dropUnique('product_sales_channel_unique');
            $table->dropColumn('id');
        });
    }
};
