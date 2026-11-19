<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/** The `items` array of a composed meal: visible foods with a portion in grams or servings. */
trait ValidatesMealItems
{
    /** @return array<string, list<mixed>> */
    protected function itemRules(): array
    {
        $userId = $this->actor()->id;

        return [
            'items' => ['sometimes', 'array', 'min:1', 'max:30'],
            'items.*.food_id' => ['required', 'integer', Rule::exists('foods', 'id')
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))],
            'items.*.grams' => ['nullable', 'numeric', 'min:1', 'max:5000', 'required_without:items.*.servings'],
            'items.*.servings' => ['nullable', 'numeric', 'min:0.1', 'max:100', 'required_without:items.*.grams'],
        ];
    }

    /** @return list<array{food_id: int, grams?: float|int|null, servings?: float|int|null}>|null */
    public function items(): ?array
    {
        $items = $this->validated('items');

        return is_array($items) ? array_values($items) : null;
    }

    /**
     * Validated fields minus the items array — and minus client-sent totals when items exist,
     * because the composer computes those.
     *
     * @return array<string, mixed>
     */
    public function mealAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        if (isset($data['items'])) {
            unset($data['items'], $data['calories'], $data['protein'], $data['carbs'], $data['fat']);
        }

        return $data;
    }
}
