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
        Schema::table('custom_suit_orders', function (Blueprint $table) {
            $table->decimal('overhead_cost', 15, 2)->default(0)->after('labor_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_suit_orders', function (Blueprint $table) {
            $table->dropColumn('overhead_cost');
        });
    }
};
