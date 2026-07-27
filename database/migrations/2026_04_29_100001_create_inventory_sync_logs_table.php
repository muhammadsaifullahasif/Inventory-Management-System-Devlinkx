<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log for inventory sync operations.
     * Tracks every sync attempt for debugging and analytics.
     */
    public function up(): void
    {
        Schema::create('inventory_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->constrained()->cascadeOnDelete();

            // Sync details
            $table->unsignedInteger('previous_quantity')->nullable();
            $table->unsignedInteger('new_quantity');
            $table->unsignedInteger('central_stock'); // Stock at time of sync
            $table->unsignedInteger('visible_threshold'); // Visible qty threshold used

            // Status
            $table->enum('status', ['pending', 'success', 'failed', 'skipped']);
            $table->string('skip_reason')->nullable(); // Why sync was skipped
            $table->text('error_message')->nullable();
            $table->string('ebay_item_id')->nullable();

            // Trigger source
            $table->string('trigger_source')->nullable(); // 'order', 'manual', 'scheduled'
            $table->string('trigger_reference')->nullable(); // Order ID, etc.

            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['product_id', 'created_at']);
            $table->index(['sales_channel_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sync_logs');
    }
};
