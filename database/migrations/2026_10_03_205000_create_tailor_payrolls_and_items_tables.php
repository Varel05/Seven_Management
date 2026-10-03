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
        Schema::create('tailor_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('tailor_name'); // Nama Penjahit (misal: "Penjahit Adriana")
            $table->date('payroll_date'); // Tanggal slip upah (misal: 2026-10-01)
            $table->string('period_label')->nullable(); // Label periode / hari (misal: "Kamis, 1 Oktober 2026")
            $table->string('payment_method')->default('Tunai'); // Tunai / Transfer Bank BCA

            // Kalkulasi Agregat
            $table->integer('total_pieces')->default(0); // Total pcs yang dijahit
            $table->decimal('total_wage', 15, 2)->default(0); // Total Gaji Kotor
            $table->decimal('total_bon', 15, 2)->default(0); // Jumlah Bon (Potongan Kasbon)
            $table->decimal('take_home_pay', 15, 2)->default(0); // Take Home Pay Bersih

            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tailor_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tailor_payroll_id')->constrained('tailor_payrolls')->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete(); // Terhubung ke komponen direct_labor HPP
            $table->string('item_name'); // Jas Reguler, Vest, Jas Premium, Revisi, dsb
            $table->integer('quantity')->default(0); // Jumlah pcs
            $table->decimal('rate_per_piece', 15, 2)->default(0); // Biaya per pcs dari HPP
            $table->decimal('subtotal', 15, 2)->default(0); // Jumlah x Biaya per pcs
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('tailor_payroll_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tailor_payroll_id')->constrained('tailor_payrolls')->cascadeOnDelete();
            $table->date('advance_date'); // Tanggal pengambilan bon
            $table->string('description'); // Keterangan bon
            $table->decimal('amount', 15, 2)->default(0); // Jumlah pinjaman (Rp)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tailor_payroll_advances');
        Schema::dropIfExists('tailor_payroll_items');
        Schema::dropIfExists('tailor_payrolls');
    }
};
