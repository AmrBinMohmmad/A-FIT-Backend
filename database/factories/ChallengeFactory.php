<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChallengeType;
use App\Models\Challenge;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'creator_id' => User::factory(),
            'title' => fake()->sentence(3),
            'type' => ChallengeType::MostSteps,
            'goal_steps' => null,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(6)->toDateString(),
        ];
    }
}
