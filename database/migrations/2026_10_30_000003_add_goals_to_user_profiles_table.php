<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->unsignedInteger('daily_steps_goal')->default(10000)->after('daily_fat');
            $table->unsignedInteger('water_goal_ml')->default(2500)->after('daily_steps_goal');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropColumn(['daily_steps_goal', 'water_goal_ml']);
        });
    }
};
