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
        Schema::create('product_price_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('competitor_seller');
            $table->decimal('competitor_price', 10, 2);
            $table->string('currency', 10)->default('USD');
            $table->unsignedInteger('items_sold_last_month')->default(0);
            $table->string('ebay_item_id')->nullable();
            $table->string('listing_url')->nullable();
            $table->unsignedTinyInteger('rank');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['product_id', 'captured_at']);
            $table->unique(['product_id', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_price_comparisons');
    }
};
