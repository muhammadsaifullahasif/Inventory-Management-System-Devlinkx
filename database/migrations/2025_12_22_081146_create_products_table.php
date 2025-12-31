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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('barcode')->unique();
            // $table->string('barcode_image')->nullable();
            // $table->bigInteger('warehouse_id')->unsigned();
            // $table->bigInteger('rack_id')->unsigned()->nullable();
            $table->bigInteger('category_id')->unsigned();
            $table->bigInteger('brand_id')->unsigned()->nullable();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            // $table->double('regular_price', 15, 2);
            // $table->double('sale_price', 15, 2)->nullable();
            $table->double('price', 15, 2);
            $table->integer('stock_quantity')->default(0);
            // $table->integer('alert_quantity')->default(0);
            $table->string('product_image')->nullable();
            // $table->double('tax', 5, 2)->default(0);
            // $table->string('tax_type')->default('exclusive'); // exclusive or inclusive
            $table->boolean('is_featured')->default(false);
            // $table->string('weight')->nullable();
            // $table->string('length')->nullable();
            // $table->string('width')->nullable();
            // $table->string('height')->nullable();
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->enum('active_status', [1, 0])->default(1);
            $table->enum('delete_status', [1, 0])->default(0);
            $table->timestamps();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('rack_id')->references('id')->on('racks')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
