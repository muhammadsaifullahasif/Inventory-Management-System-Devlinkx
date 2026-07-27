<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index name => closure that (re)adds it.
     */
    protected function wantedIndexes(): array
    {
        return [
            'orders_order_number_unique' => fn (Blueprint $t) => $t->unique('order_number'),
            'orders_ebay_order_id_unique' => fn (Blueprint $t) => $t->unique('ebay_order_id'),
            'orders_sales_channel_id_index' => fn (Blueprint $t) => $t->index('sales_channel_id'),
            'orders_order_status_index' => fn (Blueprint $t) => $t->index('order_status'),
            'orders_payment_status_index' => fn (Blueprint $t) => $t->index('payment_status'),
            'orders_order_date_index' => fn (Blueprint $t) => $t->index('order_date'),
            'orders_created_at_index' => fn (Blueprint $t) => $t->index('created_at'),
            'orders_cancel_status_index' => fn (Blueprint $t) => $t->index('cancel_status'),
        ];
    }

    /**
     * Idempotent: on local dev, `orders` had every secondary index/unique missing
     * and one corrupted (Index_type=Corrupted) — same class of metadata-stripping
     * incident as the auto_increment issue fixed 2026-07-13. On other environments
     * (e.g. production) these indexes may already exist and be healthy, so this
     * only adds what's missing and only drops+rebuilds what's actually corrupted.
     */
    public function up(): void
    {
        $existing = collect(DB::select('SHOW INDEX FROM orders'))->keyBy('Key_name');

        $cancelStatusIdx = $existing->get('orders_cancel_status_index');
        if ($cancelStatusIdx && $cancelStatusIdx->Index_type === 'Corrupted') {
            DB::statement('ALTER TABLE orders DROP INDEX orders_cancel_status_index');
            $existing->forget('orders_cancel_status_index');
        }

        foreach ($this->wantedIndexes() as $name => $adder) {
            if ($existing->has($name)) {
                continue;
            }
            Schema::table('orders', $adder);
        }
    }

    public function down(): void
    {
        $existing = collect(DB::select('SHOW INDEX FROM orders'))->keyBy('Key_name');

        $droppers = [
            'orders_order_number_unique' => fn (Blueprint $t) => $t->dropUnique(['order_number']),
            'orders_ebay_order_id_unique' => fn (Blueprint $t) => $t->dropUnique(['ebay_order_id']),
            'orders_sales_channel_id_index' => fn (Blueprint $t) => $t->dropIndex(['sales_channel_id']),
            'orders_order_status_index' => fn (Blueprint $t) => $t->dropIndex(['order_status']),
            'orders_payment_status_index' => fn (Blueprint $t) => $t->dropIndex(['payment_status']),
            'orders_order_date_index' => fn (Blueprint $t) => $t->dropIndex(['order_date']),
            'orders_created_at_index' => fn (Blueprint $t) => $t->dropIndex(['created_at']),
            'orders_cancel_status_index' => fn (Blueprint $t) => $t->dropIndex(['cancel_status']),
        ];

        foreach ($droppers as $name => $dropper) {
            if (! $existing->has($name)) {
                continue;
            }
            Schema::table('orders', $dropper);
        }
    }
};
