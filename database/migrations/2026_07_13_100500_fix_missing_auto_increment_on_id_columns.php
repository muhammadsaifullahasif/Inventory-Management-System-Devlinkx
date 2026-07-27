<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs a widespread schema drift found while building the return module:
 * `id` on most tables was missing AUTO_INCREMENT (some missing PRIMARY KEY
 * entirely), which silently broke inserts app-wide and every FK pointing at
 * those tables. All id values were verified unique/non-null on the
 * environment this was first found on before it ran there — this is a pure
 * additive repair, no data changes.
 *
 * Written defensively: checks each table's actual current state (Key/Extra
 * on the id column) before altering, since different environments (local
 * dev vs prod) have drifted differently — some already have a PRIMARY KEY,
 * some don't, so this never assumes either. `job_batches` (uuid pk) is
 * intentionally skipped — its id is a string, not auto-incrementing.
 */
return new class extends Migration
{
    private array $bigIntIdTables = [
        'bills', 'bill_items', 'brands', 'categories', 'chart_of_accounts',
        'dashboard_settings', 'ebay_import_logs', 'failed_jobs', 'inventory_sync_logs',
        'jobs', 'journal_entries', 'journal_entry_lines', 'order_items',
        'order_metas', 'payments', 'permissions', 'personal_access_tokens', 'products',
        'product_bundle_components', 'product_metas', 'product_stocks', 'purchases',
        'purchase_items', 'racks', 'roles', 'sales_channels', 'sales_channel_product',
        'shippings', 'suppliers', 'users', 'warehouses',
    ];

    public function up(): void
    {
        foreach ($this->bigIntIdTables as $table) {
            if (!Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $column = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'");
            if (!$column) {
                continue;
            }

            $hasPrimaryKey = $column->Key === 'PRI';
            $hasAutoIncrement = str_contains(strtolower($column->Extra ?? ''), 'auto_increment');

            if ($hasAutoIncrement && $hasPrimaryKey) {
                continue;
            }

            $sql = "ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT";
            if (!$hasPrimaryKey) {
                $sql .= ', ADD PRIMARY KEY (`id`)';
            }

            DB::statement($sql);
        }

        // sessions.id is a string session key, not auto-incrementing — just needs its PK back.
        if (Illuminate\Support\Facades\Schema::hasTable('sessions')) {
            $column = DB::selectOne("SHOW COLUMNS FROM `sessions` WHERE Field = 'id'");
            if ($column && $column->Key !== 'PRI') {
                DB::statement('ALTER TABLE `sessions` ADD PRIMARY KEY (`id`)');
            }
        }
    }

    public function down(): void
    {
        // Repair migration — intentionally not reversed automatically since each
        // environment's pre-repair state differed. Restore from backup if needed.
    }
};
