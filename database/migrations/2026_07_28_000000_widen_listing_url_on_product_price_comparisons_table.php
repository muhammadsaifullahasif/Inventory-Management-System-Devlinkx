<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * eBay item URLs carry a long `amdata` tracking query param that
     * routinely exceeds 255 chars, causing insert failures.
     */
    public function up(): void
    {
        Schema::table('product_price_comparisons', function (Blueprint $table) {
            $table->text('listing_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_price_comparisons', function (Blueprint $table) {
            $table->string('listing_url')->nullable()->change();
        });
    }
};
