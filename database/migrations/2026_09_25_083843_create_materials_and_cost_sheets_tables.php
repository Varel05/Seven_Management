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
        // 1. Master Bahan Baku, Pembantu, Aksesoris, Tenaga Kerja & Overhead
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->enum('category', [
                'raw_material',
                'supporting_material',
                'accessory',
                'direct_labor',
                'overhead',
            ])->default('raw_material')->index();
            $table->string('unit', 50)->default('pcs');
            $table->decimal('standard_cost', 15, 2)->default(0);
            $table->decimal('stock', 10, 3)->default(0); // Stok fisik bahan
            $table->decimal('min_stock', 10, 3)->default(0); // Batas minimal stok
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Kartu Riwayat Mutasi Stok Bahan Baku
        Schema::create('material_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment'])->index(); // in = pembelian/masuk, out = produksi/keluar, adjustment = opname
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('reference_type')->nullable(); // 'production_batch', 'custom_order', 'purchase', 'adjustment'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // 3. Header Kartu HPP / Bill of Materials (BOM)
        Schema::create('cost_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('category', 100)->default('jas_reguler')->index();
            $table->string('fabric_type', 100)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 4. Varian Ukuran & Rekapitulasi HPP (Size S, M, L, XL, All Size)
        Schema::create('cost_sheet_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_sheet_id')->constrained('cost_sheets')->cascadeOnDelete();
            $table->string('size', 50)->default('All Size');
            $table->decimal('total_material_cost', 15, 2)->default(0);
            $table->decimal('total_labor_cost', 15, 2)->default(0);
            $table->decimal('total_overhead_cost', 15, 2)->default(0);
            $table->decimal('total_cost_price', 15, 2)->default(0); // Total HPP (Material + Labor + Overhead)
            $table->decimal('suggested_selling_price', 15, 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['cost_sheet_id', 'size']);
        });

        // 5. Detail Resep Komponen Biaya (BOM Items)
        Schema::create('cost_sheet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_sheet_variant_id')->constrained('cost_sheet_variants')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('quantity', 10, 3)->default(1.000);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_sheet_items');
        Schema::dropIfExists('cost_sheet_variants');
        Schema::dropIfExists('cost_sheets');
        Schema::dropIfExists('material_stock_movements');
        Schema::dropIfExists('materials');
    }
};
