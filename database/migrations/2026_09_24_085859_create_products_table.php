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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // SKU / Barcode
            $table->string('name');
            $table->string('category')->default('jas_blazer_pria');
            $table->string('size')->default('All Size');
            $table->string('color')->nullable();
            $table->decimal('cost_price', 15, 2)->default(0); // HPP
            $table->decimal('selling_price', 15, 2)->default(0); // Harga Jual
            $table->integer('stock')->default(0); // Stok Tersedia
            $table->integer('min_stock')->default(5); // Alert Stok Menipis
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
