<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MealType;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meal>
 */
class MealFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'calories' => fake()->numberBetween(120, 900),
            'protein' => fake()->numberBetween(5, 60),
            'carbs' => fake()->numberBetween(5, 120),
            'fat' => fake()->numberBetween(2, 40),
            'meal_type' => MealType::Lunch,
            'eaten_on' => now()->toDateString(),
        ];
    }
}
