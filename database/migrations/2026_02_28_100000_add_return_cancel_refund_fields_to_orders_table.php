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
        Schema::table('orders', function (Blueprint $table) {
            // Return tracking fields
            $table->string('return_status')->nullable()->after('cancel_status');
            $table->string('return_id')->nullable()->after('return_status');
            $table->string('return_reason')->nullable()->after('return_id');
            $table->timestamp('return_requested_at')->nullable()->after('return_reason');
            $table->timestamp('return_closed_at')->nullable()->after('return_requested_at');

            // Refund tracking fields
            $table->string('refund_status')->nullable()->after('return_closed_at');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_status');
            $table->decimal('total_refunded', 10, 2)->nullable()->default(0)->after('refund_amount');
            $table->timestamp('refund_initiated_at')->nullable()->after('total_refunded');
            $table->timestamp('refund_completed_at')->nullable()->after('refund_initiated_at');

            // Cancellation tracking fields
            $table->string('cancellation_id')->nullable()->after('refund_completed_at');
            $table->string('cancellation_reason')->nullable()->after('cancellation_id');
            $table->string('cancellation_initiated_by')->nullable()->after('cancellation_reason');
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_initiated_by');
            $table->timestamp('cancellation_closed_at')->nullable()->after('cancellation_requested_at');

            // Add indexes for common queries
            $table->index('return_status');
            $table->index('refund_status');
            $table->index('return_id');
            $table->index('cancellation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['return_status']);
            $table->dropIndex(['refund_status']);
            $table->dropIndex(['return_id']);
            $table->dropIndex(['cancellation_id']);

            // Drop columns
            $table->dropColumn([
                'return_status',
                'return_id',
                'return_reason',
                'return_requested_at',
                'return_closed_at',
                'refund_status',
                'refund_amount',
                'total_refunded',
                'refund_initiated_at',
                'refund_completed_at',
                'cancellation_id',
                'cancellation_reason',
                'cancellation_initiated_by',
                'cancellation_requested_at',
                'cancellation_closed_at',
            ]);
        });
    }
};
