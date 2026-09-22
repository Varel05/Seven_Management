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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position')->default('Staff');
            $table->string('phone')->nullable();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->integer('current_points')->default(0);
            $table->decimal('rate_per_point', 15, 2)->default(0);
            $table->unsignedTinyInteger('pay_day')->default(25); // Tanggal 1 - 31
            $table->foreignId('asset_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
