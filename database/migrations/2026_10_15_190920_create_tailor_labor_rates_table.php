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
        Schema::create('tailor_labor_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('unit')->default('pcs');
            $table->decimal('standard_cost', 15, 2)->default(0);
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed data standar awal
        $defaultRates = [
            [
                'code' => 'LAB-JHT-REG',
                'name' => 'Upah Penjahit Jas Reguler',
                'unit' => 'pcs',
                'standard_cost' => 50000,
                'order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'LAB-JHT-VST',
                'name' => 'Upah Penjahit Rompi / Vest',
                'unit' => 'pcs',
                'standard_cost' => 37000,
                'order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'LAB-JHT-PRM',
                'name' => 'Upah Penjahit Jas Premium',
                'unit' => 'pcs',
                'standard_cost' => 150000,
                'order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'LAB-JHT-REV',
                'name' => 'Upah Revisi / Perbaikan Jahitan',
                'unit' => 'pcs',
                'standard_cost' => 0,
                'order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'LAB-JHT-CLN',
                'name' => 'Upah Penjahit Celana Formal',
                'unit' => 'pcs',
                'standard_cost' => 30000,
                'order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'LAB-JHT-EXC',
                'name' => 'Upah Penjahit Jas Master Exclusive',
                'unit' => 'pcs',
                'standard_cost' => 250000,
                'order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('tailor_labor_rates')->insert($defaultRates);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tailor_labor_rates');
    }
};
