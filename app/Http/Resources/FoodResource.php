<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Food;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Food
 *
 * Both names travel so the client shows the active language and falls back to the other;
 * `per_serving` is derived here so list rows never do arithmetic.
 */
class FoodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $serving = $this->portion($this->serving_grams);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'brand' => $this->brand,
            'category' => $this->category->value,
            'source' => $this->source->value,
            'barcode' => $this->barcode,
            'generic_id' => $this->generic_id,
            'variants_count' => (int) ($this->variants_count ?? 0),
            'serving_grams' => $this->serving_grams,
            'serving_label' => $this->serving_label,
            'serving_label_ar' => $this->serving_label_ar,
            'per_100g' => [
                'calories' => $this->calories,
                'protein' => $this->protein,
                'carbs' => $this->carbs,
                'fat' => $this->fat,
            ],
            'per_serving' => $serving,
            'is_verified' => $this->is_verified,
            'is_mine' => $this->user_id !== null && $this->user_id === $request->user()?->getAuthIdentifier(),
        ];
    }
}
