<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row settings table (read via MessageSetting::current()).
        Schema::create('message_settings', function (Blueprint $table) {
            $table->id();
            $table->string('timezone', 64);
            // {"mon": {"open": "08:00", "close": "17:00"}, ..., "sun": null}  (null = closed all day)
            $table->json('office_hours')->nullable();
            $table->boolean('auto_reply_enabled')->default(false);
            $table->text('auto_reply_text')->nullable();
            // Minimum hours between auto-replies in the same thread (loop / rate-limit guard).
            $table->unsignedSmallInteger('auto_reply_cooldown_hours')->default(12);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_settings');
    }
};
