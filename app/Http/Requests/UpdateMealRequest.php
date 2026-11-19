<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MealType;
use App\Http\Requests\Concerns\ValidatesMealItems;
use Illuminate\Validation\Rule;

class UpdateMealRequest extends AuthenticatedRequest
{
    use ValidatesMealItems;

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'min:2', 'max:80'],
            'calories' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'protein' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'carbs' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'fat' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'meal_type' => ['sometimes', Rule::enum(MealType::class)],
            'eaten_on' => ['sometimes', 'date_format:Y-m-d', 'after:'.now()->subYear()->toDateString(), 'before:'.now()->addDays(2)->toDateString()],
            ...$this->itemRules(),
        ];
    }
}
