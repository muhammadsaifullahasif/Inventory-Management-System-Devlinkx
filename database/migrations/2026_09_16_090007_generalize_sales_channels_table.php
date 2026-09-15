<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_channels', function (Blueprint $table) {
            $table->string('type')->default('ebay')->after('name');
            $table->json('provider_config')->nullable()->after('ru_name');
        });

        // Every existing row today is an eBay channel — make that explicit.
        DB::table('sales_channels')->update(['type' => 'ebay']);

        // Move eBay's OAuth redirect-name concept into the generic provider_config bucket.
        DB::statement("
            UPDATE sales_channels
            SET provider_config = JSON_OBJECT('ru_name', ru_name)
            WHERE ru_name IS NOT NULL
        ");

        Schema::table('sales_channels', function (Blueprint $table) {
            $table->dropColumn('ru_name');
        });

        Schema::table('sales_channels', function (Blueprint $table) {
            $table->renameColumn('ebay_user_id', 'external_account_id');
            $table->renameColumn('ebay_user_ids', 'external_account_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_channels', function (Blueprint $table) {
            $table->renameColumn('external_account_id', 'ebay_user_id');
            $table->renameColumn('external_account_ids', 'ebay_user_ids');
        });

        Schema::table('sales_channels', function (Blueprint $table) {
            $table->string('ru_name')->nullable()->after('client_secret');
        });

        DB::statement("
            UPDATE sales_channels
            SET ru_name = JSON_UNQUOTE(JSON_EXTRACT(provider_config, '$.ru_name'))
            WHERE provider_config IS NOT NULL
        ");

        Schema::table('sales_channels', function (Blueprint $table) {
            $table->dropColumn('provider_config');
            $table->dropColumn('type');
        });
    }
};
