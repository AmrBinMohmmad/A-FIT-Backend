<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MealType;
use App\Http\Requests\Concerns\ValidatesMealItems;
use Illuminate\Validation\Rule;

/**
 * Two shapes: typed macros (name + calories required) or composed from catalog `items`
 * (then the name is optional and every number is computed server-side).
 */
class StoreMealRequest extends AuthenticatedRequest
{
    use ValidatesMealItems;

    public function rules(): array
    {
        return [
            'name' => ['required_without:items', 'nullable', 'string', 'min:2', 'max:80'],
            'calories' => ['required_without:items', 'nullable', 'integer', 'min:0', 'max:10000'],
            'protein' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'carbs' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'fat' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'meal_type' => ['required', Rule::enum(MealType::class)],
            'eaten_on' => ['required', 'date_format:Y-m-d', 'after:'.now()->subYear()->toDateString(), 'before:'.now()->addDays(2)->toDateString()],
            ...$this->itemRules(),
        ];
    }
}
