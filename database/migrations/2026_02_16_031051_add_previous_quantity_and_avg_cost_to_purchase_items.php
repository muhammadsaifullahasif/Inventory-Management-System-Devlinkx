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
        Schema::table('purchase_items', function (Blueprint $table) {
            // Stock quantity at this location before this purchase was added (audit snapshot)
            $table->decimal('previous_quantity', 15, 4)->default(0)->after('quantity');
            // Weighted average cost calculated at the time this purchase was saved
            $table->decimal('avg_cost', 15, 4)->default(0)->after('previous_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['previous_quantity', 'avg_cost']);
        });
    }
};
