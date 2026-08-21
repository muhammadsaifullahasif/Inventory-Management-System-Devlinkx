<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command')->unique();
            $table->string('status');
            $table->timestamp('last_ran_at')->nullable();
            $table->unsignedInteger('runtime_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_task_runs');
    }
};
