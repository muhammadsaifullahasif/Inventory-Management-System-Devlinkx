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
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_id');
            $table->string('client_secret');
            $table->string('ru_name');
            $table->string('user_scopes');
            $table->text('authorization_code')->nullable();
            $table->string('access_token')->nullable();
            $table->dateTime('access_token_expires_at')->nullable();
            $table->string('refresh_token')->nullable();
            $table->dateTime('refresh_token_expires_at')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('status')->default('active');
            $table->enum('active_status', [1, 0])->default(1);
            $table->enum('delete_status', [1, 0])->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_channels');
    }
};
