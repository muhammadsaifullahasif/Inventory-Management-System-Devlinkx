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
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('duties_customs', 12, 2)->default(0)->after('purchase_note')
                ->comment('Duties & Customs charges for this purchase');
            $table->decimal('freight_charges', 12, 2)->default(0)->after('duties_customs')
                ->comment('Freight/Shipping charges for this purchase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['duties_customs', 'freight_charges']);
        });
    }
};
