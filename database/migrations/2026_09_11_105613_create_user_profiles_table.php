<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use function Laravel\Prompts\table;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('gender', ['male', 'female']);
            $table->integer('age');
            $table->integer('height');
            $table->integer('current_weight');
            $table->integer('target_weight');
            $table->enum('activity_level', ['sedentary', 'light', 'moderate', 'active', 'extra_active']);
            $table->enum('goal_type', ['lose', 'maintain', 'gain'])->default('maintain');
            $table->integer('daily_calories');
            $table->integer('daily_protein');
            $table->integer('daily_carbs');
            $table->integer('daily_fat');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
