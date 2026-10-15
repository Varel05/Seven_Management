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
        Schema::table('tailor_payrolls', function (Blueprint $table) {
            $table->foreignId('tailor_id')->nullable()->after('employee_id')->constrained('tailors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tailor_payrolls', function (Blueprint $table) {
            $table->dropForeign(['tailor_id']);
            $table->dropColumn('tailor_id');
        });
    }
};
