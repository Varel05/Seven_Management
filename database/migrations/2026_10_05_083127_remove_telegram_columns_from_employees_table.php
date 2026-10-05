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
            $table->dropUnique(['telegram_user_id']);
            $table->dropColumn(['telegram_user_id', 'telegram_username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('telegram_user_id')->nullable()->unique()->after('phone');
            $table->string('telegram_username')->nullable()->after('telegram_user_id');
        });
    }
};
