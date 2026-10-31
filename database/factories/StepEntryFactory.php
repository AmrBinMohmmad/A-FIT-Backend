<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StepSource;
use App\Models\StepEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StepEntry>
 */
class StepEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'steps' => fake()->numberBetween(1000, 15000),
            'source' => StepSource::HealthKit,
            'synced_at' => now(),
        ];
    }
}
