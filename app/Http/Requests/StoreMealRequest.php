<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MealType;
use Illuminate\Validation\Rule;

class StoreMealRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'calories' => ['required', 'integer', 'min:0', 'max:10000'],
            'protein' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'carbs' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'fat' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'meal_type' => ['required', Rule::enum(MealType::class)],
            'eaten_on' => ['required', 'date_format:Y-m-d', 'after:'.now()->subYear()->toDateString(), 'before:'.now()->addDays(2)->toDateString()],
        ];
    }
}
