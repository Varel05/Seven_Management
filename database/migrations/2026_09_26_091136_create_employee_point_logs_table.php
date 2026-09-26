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
        Schema::create('employee_point_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->integer('points');
            $table->string('category', 50)->default('manual'); // item_sale, item_rent, quantity, cod, review, cross_company, manual
            $table->string('reference_type', 50)->nullable();  // retail_sale, custom_order
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('actor')->nullable();               // Telegram:ID or Admin
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_point_logs');
    }
};
