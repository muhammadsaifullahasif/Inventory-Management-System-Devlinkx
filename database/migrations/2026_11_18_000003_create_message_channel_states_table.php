<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per sales channel: sync cursor, health, and per-store switches.
        Schema::create('message_channel_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_channel_id')->unique()->constrained('sales_channels')->cascadeOnDelete();

            $table->boolean('messaging_enabled')->default(true);
            // Off by default: auto-replies send real messages to buyers, so it is opt-in per store.
            $table->boolean('auto_reply_enabled')->default(false);

            // Opaque, driver-defined sync position (eBay: ISO-8601 timestamp).
            $table->string('cursor')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_channel_states');
    }
};
