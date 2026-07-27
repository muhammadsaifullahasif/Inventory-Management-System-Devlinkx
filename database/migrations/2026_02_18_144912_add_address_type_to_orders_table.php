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
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('address_type', ['UNKNOWN', 'BUSINESS', 'RESIDENTIAL', 'MIXED'])
                  ->default('UNKNOWN')
                  ->nullable()
                  ->after('shipping_country_name');
            $table->timestamp('address_validated_at')->nullable()->after('address_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['address_type', 'address_validated_at']);
        });
    }
};
