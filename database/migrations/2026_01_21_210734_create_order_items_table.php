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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');

            // eBay specific identifiers
            $table->string('ebay_item_id')->nullable();
            $table->string('ebay_transaction_id')->nullable();
            $table->string('ebay_line_item_id')->nullable();

            // Item details
            $table->string('sku')->nullable();
            $table->string('title');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            // Variation info (for products with variations)
            $table->json('variation_attributes')->nullable();

            // Status
            $table->boolean('inventory_updated')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('order_id');
            $table->index('product_id');
            $table->index('ebay_item_id');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
