<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FoodCategory;
use App\Enums\FoodSource;
use App\Models\Food;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Food>
 */
class FoodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'generic_id' => null,
            'category' => FoodCategory::Other,
            'source' => FoodSource::Catalog,
            'name' => fake()->unique()->words(2, true),
            'name_ar' => null,
            'brand' => null,
            'serving_grams' => 100,
            'serving_label' => '100 g',
            'calories' => fake()->numberBetween(50, 400),
            'protein' => fake()->numberBetween(0, 30),
            'carbs' => fake()->numberBetween(0, 60),
            'fat' => fake()->numberBetween(0, 30),
            'is_verified' => true,
        ];
    }
}
