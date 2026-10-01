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
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('daily_rate', 15, 2)->default(0)->after('base_salary');
            $table->decimal('discipline_rate', 15, 2)->default(0)->after('daily_rate');
            $table->decimal('holiday_rate', 15, 2)->default(0)->after('discipline_rate');
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('period', 50); // Contoh: "September 2026"
            $table->date('period_start')->nullable(); // Contoh: 2026-08-26
            $table->date('period_end')->nullable(); // Contoh: 2026-09-25
            $table->string('payment_method', 50)->default('Transfer');

            // Absensi Bulanan (Input Manual)
            $table->unsignedSmallInteger('total_shifts')->default(27);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedSmallInteger('total_present')->default(27);
            $table->unsignedSmallInteger('discipline_present')->default(27);
            $table->unsignedSmallInteger('holiday_shifts')->default(0);

            // Tarif Satuan
            $table->decimal('daily_rate', 15, 2)->default(0);
            $table->decimal('discipline_rate', 15, 2)->default(0);
            $table->decimal('holiday_rate', 15, 2)->default(0);

            // Performa Closing / Penjualan
            $table->integer('closing_points')->default(0);
            $table->integer('closing_pcs')->default(0);
            $table->decimal('rate_per_point', 15, 2)->default(0);
            $table->json('closing_breakdown')->nullable(); // {SD: ..., FC: ..., JK: ..., INA: ..., BJ: ..., LM: ...}

            // Komponen Honorarium (Hasil Perhitungan)
            $table->decimal('main_salary', 15, 2)->default(0); // 1. Honor Utama (hadir x upah/hari)
            $table->decimal('discipline_bonus', 15, 2)->default(0); // 2. Bonus Disiplin (disiplin x bonus/hari)
            $table->decimal('sales_bonus', 15, 2)->default(0); // 3. Bonus Penjualan (poin/pcs x pengali)
            $table->decimal('holiday_bonus', 15, 2)->default(0); // 4. Bonus Tanggal Merah
            $table->decimal('allowance_total', 15, 2)->default(0); // Tunjangan Lain
            $table->decimal('take_home_pay', 15, 2)->default(0); // Total Honor / Take Home Pay

            $table->text('notes')->nullable();
            $table->string('hrd_name', 100)->default('Ari Husbana');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payrolls');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['daily_rate', 'discipline_rate', 'holiday_rate']);
        });
    }
};
