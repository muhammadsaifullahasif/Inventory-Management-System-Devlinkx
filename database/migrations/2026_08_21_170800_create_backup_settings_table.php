<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('schedule_enabled')->default(true);
            $table->string('notification_email')->nullable();
            $table->unsignedInteger('keep_daily_backups_for_days')->default(7);
            $table->unsignedInteger('keep_weekly_backups_for_weeks')->default(4);
            $table->unsignedInteger('keep_monthly_backups_for_months')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
