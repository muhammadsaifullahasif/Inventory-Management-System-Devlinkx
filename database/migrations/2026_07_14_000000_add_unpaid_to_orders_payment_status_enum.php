<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('pending', 'unpaid', 'paid', 'refunded', 'failed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE orders SET payment_status = 'pending' WHERE payment_status = 'unpaid'");
        DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('pending', 'paid', 'refunded', 'failed') NOT NULL DEFAULT 'pending'");
    }
};
