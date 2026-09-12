<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('archive_enabled')->default(true);
            $table->unsignedInteger('retention_days')->default(90);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_settings');
    }
};
