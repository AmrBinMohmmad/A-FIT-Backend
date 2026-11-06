<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Per-day meal totals for a history window (GET meals/summary).
 *
 * @property string $eaten_on
 * @property int $meals_count
 * @property int $calories
 * @property int $protein
 * @property int $carbs
 * @property int $fat
 */
class DayTotalsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => (string) $this->eaten_on,
            'meals_count' => (int) $this->meals_count,
            'calories' => (int) $this->calories,
            'protein' => (int) $this->protein,
            'carbs' => (int) $this->carbs,
            'fat' => (int) $this->fat,
        ];
    }
}
