<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            // Null for internal notes and for outbound messages not yet confirmed by the platform.
            $table->string('external_message_id', 100)->nullable();

            // inbound | outbound | internal_note
            $table->string('direction', 20);
            // buyer | seller | platform | auto_reply | team
            $table->string('sender_kind', 20);
            $table->string('sender_name')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->longText('body');
            $table->json('attachments')->nullable();

            // sent | failed
            $table->string('delivery_status', 20)->default('sent');
            $table->text('error')->nullable();
            $table->boolean('is_auto_reply')->default(false);

            $table->timestamp('sent_at')->nullable();
            // For inbound messages: when it was read (null = unread).
            $table->timestamp('read_at')->nullable();

            $table->json('raw')->nullable();
            $table->timestamps();

            // MySQL allows many NULLs in a unique index, so notes and pending sends don't collide.
            $table->unique(['thread_id', 'external_message_id'], 'messages_thread_external_unique');
            $table->index(['thread_id', 'sent_at'], 'messages_thread_sent_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
