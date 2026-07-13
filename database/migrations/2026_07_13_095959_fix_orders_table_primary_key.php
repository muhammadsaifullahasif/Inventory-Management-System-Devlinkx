<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs a schema drift on the orders table: `id` is missing its
 * PRIMARY KEY / AUTO_INCREMENT, which silently breaks foreign keys
 * pointing at orders.id (e.g. order_items.order_id never actually
 * got a real FK constraint). Existing id values are confirmed unique
 * and non-null, so this is a pure additive repair — no data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `orders` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `orders` DROP PRIMARY KEY, MODIFY `id` BIGINT UNSIGNED NOT NULL');
    }
};
