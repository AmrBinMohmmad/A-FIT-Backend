<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gender' => Gender::Male,
            'age' => 30,
            'height' => 175,
            'current_weight' => 80,
            'target_weight' => 75,
            'activity_level' => ActivityLevel::Moderate,
            'goal_type' => GoalType::Lose,
            'daily_calories' => 2150,
            'daily_protein' => 150,
            'daily_carbs' => 230,
            'daily_fat' => 67,
            'daily_steps_goal' => 10000,
            'water_goal_ml' => 2500,
        ];
    }
}
