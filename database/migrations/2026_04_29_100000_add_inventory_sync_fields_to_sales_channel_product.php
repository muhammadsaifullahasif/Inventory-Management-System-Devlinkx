<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add fields for buffered inventory sync system.
     *
     * - last_synced_quantity: Tracks the quantity last pushed to eBay
     * - visible_quantity: Fixed visible quantity threshold (e.g., 10)
     * - sync_enabled: Whether auto-sync is enabled for this listing
     */
    public function up(): void
    {
        Schema::table('sales_channel_product', function (Blueprint $table) {
            // Last quantity synced to eBay (used to determine if sync needed)
            $table->unsignedInteger('last_synced_quantity')->nullable()->after('listing_format');

            // Fixed visible quantity threshold (e.g., show 10 while stock >= 10)
            $table->unsignedInteger('visible_quantity')->default(10)->after('last_synced_quantity');

            // Enable/disable auto-sync for this specific listing
            $table->boolean('sync_enabled')->default(true)->after('visible_quantity');

            // Last sync attempt timestamp (success or failure)
            $table->timestamp('last_sync_attempted_at')->nullable()->after('last_synced_at');

            // Last sync error message (if any)
            $table->text('last_sync_error')->nullable()->after('last_sync_attempted_at');

            // Index for efficient querying of listings needing sync
            $table->index(['sync_enabled', 'listing_status'], 'idx_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales_channel_product', function (Blueprint $table) {
            $table->dropIndex('idx_sync_status');
            $table->dropColumn([
                'last_synced_quantity',
                'visible_quantity',
                'sync_enabled',
                'last_sync_attempted_at',
                'last_sync_error',
            ]);
        });
    }
};
