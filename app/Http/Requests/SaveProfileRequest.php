<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use Illuminate\Validation\Rule;

/**
 * Only the physical inputs are accepted; the daily calorie/macro targets are computed
 * server-side by NutritionPlan (Tier 1.1). Step and water goals are user choices.
 */
class SaveProfileRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'gender' => ['required', Rule::enum(Gender::class)],
            'age' => ['required', 'integer', 'min:12', 'max:100'],
            'height' => ['required', 'integer', 'min:100', 'max:250'],
            'current_weight' => ['required', 'integer', 'min:30', 'max:300'],
            'target_weight' => ['required', 'integer', 'min:30', 'max:300'],
            'activity_level' => ['required', Rule::enum(ActivityLevel::class)],
            'daily_steps_goal' => ['sometimes', 'integer', 'min:1000', 'max:50000'],
            'water_goal_ml' => ['sometimes', 'integer', 'min:500', 'max:8000'],
        ];
    }
}
