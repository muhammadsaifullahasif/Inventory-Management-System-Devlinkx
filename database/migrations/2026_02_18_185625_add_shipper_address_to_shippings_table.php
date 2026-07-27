<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shippings', function (Blueprint $table) {
            $table->string('shipper_name')->nullable()->after('account_number');
            $table->string('shipper_address')->nullable()->after('shipper_name');
            $table->string('shipper_city')->nullable()->after('shipper_address');
            $table->string('shipper_state')->nullable()->after('shipper_city');
            $table->string('shipper_postal_code')->nullable()->after('shipper_state');
            $table->string('shipper_country', 2)->default('US')->after('shipper_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('shippings', function (Blueprint $table) {
            $table->dropColumn([
                'shipper_name',
                'shipper_address',
                'shipper_city',
                'shipper_state',
                'shipper_postal_code',
                'shipper_country',
            ]);
        });
    }
};
