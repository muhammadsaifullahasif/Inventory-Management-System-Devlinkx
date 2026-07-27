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
        Schema::table('sales_channels', function (Blueprint $table) {
            // Link to bank/receivable account for this sales channel (under Banks group 1000)
            $table->unsignedBigInteger('receivable_account_id')->nullable()->after('delete_status');

            // Link to sales revenue account for this sales channel (under Sales group 4000)
            $table->unsignedBigInteger('sales_account_id')->nullable()->after('receivable_account_id');

            // Foreign keys
            $table->foreign('receivable_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->onDelete('set null');

            $table->foreign('sales_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_channels', function (Blueprint $table) {
            $table->dropForeign(['receivable_account_id']);
            $table->dropForeign(['sales_account_id']);
            $table->dropColumn(['receivable_account_id', 'sales_account_id']);
        });
    }
};
