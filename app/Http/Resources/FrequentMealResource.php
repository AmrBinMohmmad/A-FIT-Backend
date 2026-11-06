<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A frequently logged meal (GET meals/frequent) — a template for one-tap logging.
 *
 * @property string $name
 * @property string $meal_type
 * @property int $times
 * @property int $calories
 * @property int $protein
 * @property int $carbs
 * @property int $fat
 */
class FrequentMealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'meal_type' => $this->meal_type,
            'times' => $this->times,
            'calories' => $this->calories,
            'protein' => $this->protein,
            'carbs' => $this->carbs,
            'fat' => $this->fat,
        ];
    }
}
