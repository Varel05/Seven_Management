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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('rental_price', 15, 2)->default(0)->after('selling_price');
            $table->boolean('is_for_rent')->default(true)->after('rental_price');
        });

        Schema::table('retail_sales', function (Blueprint $table) {
            $table->string('transaction_type')->default('sale')->after('invoice_number'); // 'sale', 'rental'
            $table->date('rental_start_date')->nullable()->after('sale_date');
            $table->date('rental_end_date')->nullable()->after('rental_start_date');
            $table->dateTime('rental_return_date')->nullable()->after('rental_end_date');
            $table->string('rental_status')->nullable()->after('rental_return_date'); // 'active', 'returned', 'overdue'
            $table->decimal('deposit_amount', 15, 2)->default(0)->after('total_cost');
        });

        Schema::table('retail_sale_items', function (Blueprint $table) {
            $table->string('transaction_type')->default('sale')->after('product_id'); // 'sale', 'rental'
            $table->decimal('unit_rental_price', 15, 2)->default(0)->after('unit_selling_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('retail_sale_items', function (Blueprint $table) {
            $table->dropColumn(['transaction_type', 'unit_rental_price']);
        });

        Schema::table('retail_sales', function (Blueprint $table) {
            $table->dropColumn([
                'transaction_type',
                'rental_start_date',
                'rental_end_date',
                'rental_return_date',
                'rental_status',
                'deposit_amount',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['rental_price', 'is_for_rent']);
        });
    }
};
