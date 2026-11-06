<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FoodCategory;
use Illuminate\Validation\Rule;

/** A private food typed from a package label (per serving or per 100 g). */
class StoreFoodRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        $userId = $this->actor()->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'name_ar' => ['nullable', 'string', 'min:2', 'max:120'],
            'brand' => ['nullable', 'string', 'max:80'],
            'category' => ['required', Rule::enum(FoodCategory::class)],
            // Only a generic (top-level) item the user can see may be the parent.
            'generic_id' => ['nullable', 'integer', Rule::exists('foods', 'id')
                ->whereNull('generic_id')
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))],
            'serving_grams' => ['required', 'numeric', 'min:1', 'max:2000'],
            'serving_label' => ['nullable', 'string', 'max:60'],
            'serving_label_ar' => ['nullable', 'string', 'max:60'],
            'per' => ['required', Rule::in(['serving', '100g'])],
            'calories' => ['required', 'numeric', 'min:0', 'max:9000'],
            'protein' => ['required', 'numeric', 'min:0', 'max:1000'],
            'carbs' => ['required', 'numeric', 'min:0', 'max:1000'],
            'fat' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }
}
