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
        Schema::create('ebay_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sales_channel_id');
            $table->integer('total_listings')->default(0);
            $table->integer('total_batches')->default(0);
            $table->integer('completed_batches')->default(0);
            $table->integer('items_inserted')->default(0);
            $table->integer('items_updated')->default(0);
            $table->integer('items_failed')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->index('sales_channel_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_import_logs');
    }
};
