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
            $table->string('role')->default('cs')->after('position'); // 'owner', 'akuntan', 'cs'
            $table->string('telegram_user_id')->nullable()->unique()->after('phone');
            $table->string('telegram_username')->nullable()->after('telegram_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['role', 'telegram_user_id', 'telegram_username']);
        });
    }
};
