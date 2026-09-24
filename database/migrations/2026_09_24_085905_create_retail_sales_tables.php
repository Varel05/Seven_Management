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
        Schema::create('retail_sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->dateTime('sale_date');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('payment_method')->default('cash'); // cash, transfer
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0); // Total HPP
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('retail_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_sale_id')->constrained('retail_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost_price', 15, 2)->default(0);
            $table->decimal('unit_selling_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retail_sale_items');
        Schema::dropIfExists('retail_sales');
    }
};
