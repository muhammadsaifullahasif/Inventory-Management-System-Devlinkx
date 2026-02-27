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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('last_name')->nullable()->change();
            $table->string('address_line_1')->nullable()->change();
            $table->string('zipcode')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('last_name')->nullable(false)->change();
            $table->string('address_line_1')->nullable(false)->change();
            $table->string('zipcode')->nullable(false)->change();
        });
    }
};
