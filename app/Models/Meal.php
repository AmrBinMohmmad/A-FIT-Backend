<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MealType;
use Database\Factories\MealFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meal extends Model
{
    /** @use HasFactory<MealFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'calories',
        'protein',
        'carbs',
        'fat',
        'meal_type',
        'eaten_on',
    ];

    protected function casts(): array
    {
        return [
            'calories' => 'integer',
            'protein' => 'integer',
            'carbs' => 'integer',
            'fat' => 'integer',
            'meal_type' => MealType::class,
            'eaten_on' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
