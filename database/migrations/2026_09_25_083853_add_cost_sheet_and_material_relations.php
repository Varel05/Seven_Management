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
        // Hubungkan Produk Retail ke Varian Kartu HPP
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('cost_sheet_variant_id')
                ->nullable()
                ->after('cost_price')
                ->constrained('cost_sheet_variants')
                ->nullOnDelete();
        });

        // Hubungkan Pesanan Jas Custom ke Master Bahan Baku & Tracking Potong Bahan
        Schema::table('custom_suit_orders', function (Blueprint $table) {
            $table->foreignId('material_id')
                ->nullable()
                ->after('fabric_type')
                ->constrained('materials')
                ->nullOnDelete();

            $table->decimal('material_meters', 10, 3)->default(0)->after('material_cost');
            $table->boolean('is_material_cut')->default(false)->after('material_meters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_suit_orders', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropColumn(['material_id', 'material_meters', 'is_material_cut']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['cost_sheet_variant_id']);
            $table->dropColumn('cost_sheet_variant_id');
        });
    }
};
