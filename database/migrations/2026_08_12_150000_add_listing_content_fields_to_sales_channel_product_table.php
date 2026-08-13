<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_channel_product', function (Blueprint $table) {
            $table->string('title')->nullable()->after('sales_channel_id');
            $table->text('description')->nullable()->after('title');
            $table->decimal('regular_price', 10, 2)->nullable()->after('description');
            $table->decimal('sale_price', 10, 2)->nullable()->after('regular_price');
        });
    }

    public function down(): void
    {
        Schema::table('sales_channel_product', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'regular_price', 'sale_price']);
        });
    }
};
