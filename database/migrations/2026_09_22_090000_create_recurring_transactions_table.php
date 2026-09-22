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
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->enum('frequency', ['monthly', 'yearly', 'weekly'])->default('monthly');
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->unsignedTinyInteger('month_of_year')->nullable();
            $table->foreignId('expense_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('asset_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('last_posted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
