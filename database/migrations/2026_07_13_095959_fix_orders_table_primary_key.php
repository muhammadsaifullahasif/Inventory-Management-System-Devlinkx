<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs a schema drift on the orders table: `id` is missing its
 * PRIMARY KEY and/or AUTO_INCREMENT, which silently breaks foreign keys
 * pointing at orders.id (e.g. order_items.order_id never actually
 * got a real FK constraint). Existing id values are confirmed unique
 * and non-null, so this is a pure additive repair — no data changes.
 *
 * Written defensively: different environments have drifted differently
 * (e.g. prod already has the PRIMARY KEY but is missing AUTO_INCREMENT),
 * so this checks current state before altering instead of assuming both
 * are missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $column = DB::selectOne("SHOW COLUMNS FROM `orders` WHERE Field = 'id'");

        $hasPrimaryKey = $column->Key === 'PRI';
        $hasAutoIncrement = str_contains(strtolower($column->Extra ?? ''), 'auto_increment');

        if ($hasAutoIncrement && $hasPrimaryKey) {
            return;
        }

        $sql = 'ALTER TABLE `orders` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';
        if (!$hasPrimaryKey) {
            $sql .= ', ADD PRIMARY KEY (`id`)';
        }

        DB::statement($sql);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `orders` DROP PRIMARY KEY, MODIFY `id` BIGINT UNSIGNED NOT NULL');
    }
};
