<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Meal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Meal */
class MealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'calories' => $this->calories,
            'protein' => $this->protein ?? 0,
            'carbs' => $this->carbs ?? 0,
            'fat' => $this->fat ?? 0,
            'meal_type' => $this->meal_type?->value ?? 'other',
            'eaten_on' => $this->eaten_on->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
