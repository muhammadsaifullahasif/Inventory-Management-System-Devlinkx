<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('ebay_import_logs', 'sales_channel_import_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('sales_channel_import_logs', 'ebay_import_logs');
    }
};
