<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One online product candidate (normalized Open Food Facts row). Shaped like FoodResource so
 * the client renders both with one row component; `food_id` is set when it is already in
 * the catalog, so adding it needs no import call.
 */
class LookupResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $p */
        $p = $this->resource;
        $factor = (float) $p['serving_grams'] / 100;

        return [
            'food_id' => $p['food_id'] ?? null,
            'barcode' => $p['barcode'],
            'name' => $p['name'],
            'name_ar' => $p['name_ar'],
            'brand' => $p['brand'],
            'image_url' => $p['image_url'],
            'category' => $p['category'],
            'source' => 'open_food_facts',
            'sold_locally' => (bool) $p['sold_locally'],
            'serving_grams' => $p['serving_grams'],
            'serving_label' => $p['serving_label'],
            'serving_label_ar' => null,
            'per_100g' => [
                'calories' => $p['calories'],
                'protein' => $p['protein'],
                'carbs' => $p['carbs'],
                'fat' => $p['fat'],
            ],
            'per_serving' => [
                'calories' => (int) round($p['calories'] * $factor),
                'protein' => (int) round($p['protein'] * $factor),
                'carbs' => (int) round($p['carbs'] * $factor),
                'fat' => (int) round($p['fat'] * $factor),
            ],
        ];
    }
}
