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
        Schema::table('product_stocks', function (Blueprint $table) {
            // Quantity before the most recent purchase was received into this location
            $table->decimal('previous_quantity', 15, 4)->default(0)->after('quantity');
            // Weighted average cost per unit across all purchases into this location
            $table->decimal('avg_cost', 15, 4)->default(0)->after('previous_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropColumn(['previous_quantity', 'avg_cost']);
        });
    }
};
