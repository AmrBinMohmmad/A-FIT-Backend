<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One food line inside a meal, with the nutrition snapshotted at logging time. */
class MealItem extends Model
{
    protected $fillable = [
        'food_id',
        'name',
        'brand',
        'grams',
        'servings',
        'calories',
        'protein',
        'carbs',
        'fat',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'grams' => 'float',
            'servings' => 'float',
            'calories' => 'integer',
            'protein' => 'integer',
            'carbs' => 'integer',
            'fat' => 'integer',
            'position' => 'integer',
        ];
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
