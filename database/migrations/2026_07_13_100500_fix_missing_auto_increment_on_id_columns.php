<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs a widespread schema drift found while building the return module:
 * `id` on most tables was missing AUTO_INCREMENT (some missing PRIMARY KEY
 * entirely), which silently broke inserts app-wide and every FK pointing at
 * those tables. All id values were verified unique/non-null before this ran
 * — this is a pure additive repair, no data changes. `job_batches` (uuid pk)
 * is intentionally left untouched.
 */
return new class extends Migration
{
    /**
     * Tables that already had a PRIMARY KEY on id, just missing AUTO_INCREMENT.
     */
    private array $missingAutoIncrementOnly = [
        'bills', 'bill_items', 'brands', 'categories', 'chart_of_accounts',
        'dashboard_settings', 'ebay_import_logs', 'failed_jobs', 'inventory_sync_logs',
        'jobs', 'journal_entries', 'journal_entry_lines', 'order_items',
    ];

    /**
     * Tables missing both PRIMARY KEY and AUTO_INCREMENT on id (bigint).
     */
    private array $missingPrimaryKeyAndAutoIncrement = [
        'order_metas', 'payments', 'permissions', 'personal_access_tokens', 'products',
        'product_bundle_components', 'product_metas', 'product_stocks', 'purchases',
        'purchase_items', 'racks', 'roles', 'sales_channels', 'sales_channel_product',
        'shippings', 'suppliers', 'users', 'warehouses',
    ];

    public function up(): void
    {
        foreach ($this->missingAutoIncrementOnly as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
        }

        foreach ($this->missingPrimaryKeyAndAutoIncrement as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)");
        }

        // sessions.id is a string session key, not auto-incrementing — just needs its PK back.
        DB::statement('ALTER TABLE `sessions` ADD PRIMARY KEY (`id`)');
    }

    public function down(): void
    {
        foreach ($this->missingAutoIncrementOnly as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL");
        }

        foreach ($this->missingPrimaryKeyAndAutoIncrement as $table) {
            DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, MODIFY `id` BIGINT UNSIGNED NOT NULL");
        }

        DB::statement('ALTER TABLE `sessions` DROP PRIMARY KEY');
    }
};
