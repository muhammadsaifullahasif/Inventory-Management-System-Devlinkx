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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->onDelete('cascade');
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->enum('nature', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->enum('type', ['group', 'account'])->default('account');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            // Bank/Cash specific fields (nullable for non-bank accounts)
            $table->boolean('is_bank_cash')->default(false);
            $table->string('account_number', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('branch', 100)->nullable();
            $table->string('iban', 50)->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['parent_id', 'type']);
            $table->index('nature');
            $table->index('is_bank_cash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
