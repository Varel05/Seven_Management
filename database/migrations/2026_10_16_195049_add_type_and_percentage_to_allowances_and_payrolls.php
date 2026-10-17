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
        Schema::table('allowances', function (Blueprint $table) {
            $table->string('type', 20)->default('allowance')->after('name');
            $table->string('calculation_type', 20)->default('fixed')->after('amount');
            $table->decimal('rate_percent', 5, 2)->nullable()->after('calculation_type');
        });

        Schema::table('employee_payrolls', function (Blueprint $table) {
            $table->decimal('deduction_total', 15, 2)->default(0)->after('allowance_total');
            $table->json('allowances_breakdown')->nullable()->after('closing_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_payrolls', function (Blueprint $table) {
            $table->dropColumn(['deduction_total', 'allowances_breakdown']);
        });

        Schema::table('allowances', function (Blueprint $table) {
            $table->dropColumn(['type', 'calculation_type', 'rate_percent']);
        });
    }
};
