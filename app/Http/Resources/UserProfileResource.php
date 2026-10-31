<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserProfile */
class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'gender' => $this->gender->value,
            'age' => $this->age,
            'height' => $this->height,
            'current_weight' => $this->current_weight,
            'target_weight' => $this->target_weight,
            'activity_level' => $this->activity_level->value,
            'goal_type' => $this->goal_type->value,
            'daily_calories' => $this->daily_calories,
            'daily_protein' => $this->daily_protein,
            'daily_carbs' => $this->daily_carbs,
            'daily_fat' => $this->daily_fat,
            'daily_steps_goal' => $this->daily_steps_goal,
            'water_goal_ml' => $this->water_goal_ml,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
