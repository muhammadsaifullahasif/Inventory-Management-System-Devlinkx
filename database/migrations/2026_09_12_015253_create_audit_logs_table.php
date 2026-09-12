<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same column set as audit_log_archives — rows move between the two
     * tables verbatim during archival (see ArchiveAuditLogsJob).
     */
    private function columns(Blueprint $table): void
    {
        $table->id();
        $table->uuid('uuid')->unique();
        $table->string('event'); // created|updated|deleted|login|logout|login_failed|custom
        $table->string('auditable_type')->nullable();
        $table->unsignedBigInteger('auditable_id')->nullable();
        $table->string('actor_type'); // user|api|webhook|queue|console|system
        $table->unsignedBigInteger('actor_id')->nullable();
        $table->string('actor_label')->nullable();
        $table->string('ip_address')->nullable();
        $table->text('user_agent')->nullable();
        $table->string('url')->nullable();
        $table->string('http_method', 10)->nullable();
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->json('context')->nullable();
        $table->timestamp('created_at')->nullable();

        $table->index('created_at');
        $table->index(['auditable_type', 'auditable_id']);
        $table->index('actor_type');
        $table->index('event');
    }

    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $this->columns($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
