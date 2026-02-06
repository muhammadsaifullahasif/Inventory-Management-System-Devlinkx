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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->date('payment_date');
            $table->foreignId('bill_id')->constrained('bills')->onDelete('restrict');
            $table->foreignId('payment_account_id')->constrained('chart_of_accounts')->onDelete('restrict');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 20)->default('bank');
            $table->string('reference', 100)->nullable();
            $table->enum('status', ['draft', 'posted'])->default('posted');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['status', 'payment_date']);
            $table->index('bill_id');
            $table->index('payment_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
