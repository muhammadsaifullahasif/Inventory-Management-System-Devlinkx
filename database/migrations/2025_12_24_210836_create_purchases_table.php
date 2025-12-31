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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number');
            $table->bigInteger('supplier_id')->unsigned();
            $table->bigInteger('warehouse_id')->unsigned();
            $table->text('purchase_note')->nullable();
            $table->string('purchase_status')->default('pending');
            $table->enum('active_status', [1, 0])->default(1);
            $table->enum('delete_status', [1, 0])->default(0);
            $table->timestamps();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->unique(['purchase_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
