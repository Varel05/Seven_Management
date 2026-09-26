<?php

use App\Enums\EmployeeRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan baris data yang ada memiliki nilai role yang valid
        DB::table('employees')
            ->whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => EmployeeRole::Staff->value]);

        Schema::table('employees', function (Blueprint $table) {
            $table->enum('role', EmployeeRole::values())
                ->default(EmployeeRole::Staff->value)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('role')
                ->default(EmployeeRole::Cs->value)
                ->change();
        });
    }
};
