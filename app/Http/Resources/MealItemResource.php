<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MealItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MealItem */
class MealItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'food_id' => $this->food_id,
            'name' => $this->name,
            'brand' => $this->brand,
            'grams' => $this->grams,
            'servings' => $this->servings,
            'calories' => $this->calories,
            'protein' => $this->protein,
            'carbs' => $this->carbs,
            'fat' => $this->fat,
        ];
    }
}
