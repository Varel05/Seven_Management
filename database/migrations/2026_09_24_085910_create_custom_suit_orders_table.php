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
        Schema::create('custom_suit_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->date('order_date');
            $table->date('due_date')->nullable();
            $table->string('suit_type')->default('jas_blazer_pria');
            $table->string('fabric_type')->nullable();
            $table->string('color')->nullable();
            $table->json('body_measurements')->nullable();
            $table->string('reference_image')->nullable();
            $table->json('ai_estimation')->nullable();
            $table->decimal('material_cost', 15, 2)->default(0);
            $table->decimal('labor_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0); // HPP Custom
            $table->decimal('total_price', 15, 2)->default(0); // Harga ke Klien
            $table->decimal('down_payment', 15, 2)->default(0); // Uang Muka (DP)
            $table->string('payment_status')->default('unpaid'); // unpaid, partial_dp, paid
            $table->string('production_status')->default('consultation'); // consultation, measurement, cutting_sewing, fitting, finishing, ready, completed, cancelled
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('source')->default('web'); // telegram, web
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_suit_orders');
    }
};
