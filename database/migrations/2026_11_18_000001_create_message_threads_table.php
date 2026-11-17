<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_channel_id')->constrained('sales_channels')->cascadeOnDelete();
            // The platform's own conversation id. Unique per channel, not globally.
            $table->string('external_thread_id');

            // pre_purchase | post_purchase | platform_system | unknown
            $table->string('thread_kind', 30)->default('unknown');

            $table->string('buyer_username')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_external_id')->nullable();

            $table->string('subject')->nullable();
            // What the thread is about on the platform (e.g. LISTING) and its id (e.g. eBay item id).
            $table->string('reference_type', 50)->nullable();
            $table->string('external_reference_id', 64)->nullable();

            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            // pending (needs team reply) | awaiting_buyer | resolved
            $table->string('status', 20)->default('pending');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview')->nullable();
            // inbound | outbound
            $table->string('last_message_direction', 20)->nullable();
            $table->timestamp('auto_replied_at')->nullable();
            $table->timestamp('external_updated_at')->nullable();

            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['sales_channel_id', 'external_thread_id'], 'message_threads_channel_external_unique');
            $table->index(['sales_channel_id', 'last_message_at'], 'message_threads_channel_last_message_index');
            $table->index(['status', 'assigned_user_id'], 'message_threads_status_assignee_index');
            $table->index(['sales_channel_id', 'buyer_username'], 'message_threads_channel_buyer_index');
            $table->index('external_reference_id', 'message_threads_reference_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_threads');
    }
};
