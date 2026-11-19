<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_channel_states', function (Blueprint $table) {
            // Set when the platform said the store lacks the messaging permission; cleared by the next good sync.
            $table->boolean('needs_reauthorization')->default(false)->after('auto_reply_enabled');
        });

        Schema::table('message_settings', function (Blueprint $table) {
            // Resolved conversations with no activity for this many days are deleted. Null = keep forever.
            $table->unsignedSmallInteger('retention_days')->nullable()->after('auto_reply_cooldown_hours');
        });
    }

    public function down(): void
    {
        Schema::table('message_settings', function (Blueprint $table) {
            $table->dropColumn('retention_days');
        });

        Schema::table('message_channel_states', function (Blueprint $table) {
            $table->dropColumn('needs_reauthorization');
        });
    }
};
