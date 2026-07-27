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
        Schema::create('product_bundle_components', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('bundle_product_id')->unsigned();
            $table->bigInteger('component_product_id')->unsigned();
            $table->integer('quantity_required')->default(1)->comment('How many of this component needed per bundle');
            $table->timestamps();

            $table->foreign('bundle_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('component_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['bundle_product_id', 'component_product_id'], 'bundle_component_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_bundle_components');
    }
};
