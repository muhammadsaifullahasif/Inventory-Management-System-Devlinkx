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
        Schema::table('order_items', function (Blueprint $table) {
            $table->bigInteger('bundle_product_id')->unsigned()->nullable()->after('product_id');
            $table->string('bundle_name')->nullable()->after('bundle_product_id');
            $table->boolean('is_bundle_summary')->default(false)->after('bundle_name')->comment('Bundle header item for display only');

            $table->foreign('bundle_product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['bundle_product_id']);
            $table->dropColumn(['bundle_product_id', 'bundle_name', 'is_bundle_summary']);
        });
    }
};
