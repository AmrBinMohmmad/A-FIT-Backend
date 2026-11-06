<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FoodCategory;
use Illuminate\Validation\Rule;

class SearchFoodsRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', Rule::enum(FoodCategory::class)],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function category(): ?FoodCategory
    {
        $value = $this->validated('category');

        return is_string($value) ? FoodCategory::from($value) : null;
    }
}
