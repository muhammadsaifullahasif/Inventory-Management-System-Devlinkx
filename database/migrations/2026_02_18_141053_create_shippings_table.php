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
        Schema::create('shippings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->json('credentials')->nullable();
            $table->text('authorization_code')->nullable();
            $table->text('access_token')->nullable();
            $table->dateTime('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('refresh_token_expires_at')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('account_number')->nullable();
            $table->boolean('is_sandbox')->default(false);
            $table->boolean('is_address_validation')->default(false);
            $table->string('api_endpoint')->nullable();
            $table->string('sandbox_endpoint')->nullable();
            $table->string('tracking_url')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('default_service')->nullable();
            $table->string('weight_unit')->default('lbs');
            $table->string('dimension_unit')->default('inches');
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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('shippings');
        Schema::enableForeignKeyConstraints();
    }
};
