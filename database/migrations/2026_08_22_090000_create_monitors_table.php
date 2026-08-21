<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->unique();
            $table->unsignedInteger('check_interval_minutes')->default(5);
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->boolean('check_ssl')->default(true);
            $table->boolean('is_active')->default(true);

            $table->string('uptime_status')->default('not_yet_checked');
            $table->timestamp('uptime_last_checked_at')->nullable();
            $table->timestamp('uptime_status_changed_at')->nullable();
            $table->unsignedInteger('uptime_check_response_time_ms')->nullable();
            $table->text('uptime_check_failure_reason')->nullable();

            $table->string('ssl_status')->default('not_yet_checked');
            $table->date('ssl_expiration_date')->nullable();
            $table->text('ssl_check_failure_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
