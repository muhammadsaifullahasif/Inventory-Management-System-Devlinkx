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
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('sales_channel_id')->nullable()->constrained()->onDelete('set null');

            // Source of the return
            $table->string('source')->default('manual'); // manual, ebay
            $table->string('ebay_return_id')->nullable()->unique();

            // Status: requested, approved, declined, item_received, refunded, closed
            $table->string('status')->default('requested');
            $table->string('reason')->nullable();
            $table->text('buyer_comments')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('refund_amount', 10, 2)->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
            $table->index('source');
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');

            $table->integer('quantity')->default(1);
            $table->boolean('restocked')->default(false);
            $table->timestamp('restocked_at')->nullable();

            $table->timestamps();

            $table->index('order_return_id');
            $table->index('order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
    }
};
