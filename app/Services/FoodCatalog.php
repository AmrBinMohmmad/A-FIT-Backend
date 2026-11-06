<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FoodCategory;
use App\Enums\FoodSource;
use App\Models\Food;
use App\Models\MealItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Searching and extending the food catalog. Everything here is scoped to what the user may
 * see: the shared catalog plus their own private foods (cross-user rows do not exist for them).
 */
final class FoodCatalog
{
    public const RECENT_LIMIT = 20;

    /**
     * Name / Arabic name / brand search, or an exact barcode when the query is all digits.
     * Generic items come before their variants and prefix matches before substring matches,
     * so "egg" lists "Egg" first and the kinds of eggs under it.
     */
    public function search(User $user, ?string $q, ?FoodCategory $category, int $perPage): LengthAwarePaginator
    {
        $q = trim((string) $q);

        $query = Food::query()
            ->visibleTo($user)
            ->withCount(['variants' => fn (Builder $v) => $v->visibleTo($user)])
            ->when($category, fn (Builder $b) => $b->where('category', $category->value));

        if ($q !== '' && preg_match('/^\d{8,14}$/', $q) === 1) {
            return $query->where('barcode', $q)->orderBy('id')->paginate($perPage);
        }

        if ($q !== '') {
            $like = '%'.self::escapeLike($q).'%';
            $prefix = self::escapeLike($q).'%';
            $query
                ->where(fn (Builder $b) => $b
                    ->where('name', 'like', $like)
                    ->orWhere('name_ar', 'like', $like)
                    ->orWhere('brand', 'like', $like))
                ->orderByRaw('CASE WHEN name LIKE ? OR name_ar LIKE ? THEN 0 ELSE 1 END', [$prefix, $prefix]);
        }

        return $query
            ->orderByRaw('CASE WHEN generic_id IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('is_verified')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * The foods the user logged most recently (distinct, newest first) — the empty-query
     * state of the picker, so a daily breakfast is two taps.
     *
     * @return Collection<int, Food>
     */
    public function recent(User $user): Collection
    {
        $latest = MealItem::query()
            ->join('meals', 'meals.id', '=', 'meal_items.meal_id')
            ->where('meals.user_id', $user->id)
            ->whereNotNull('meal_items.food_id')
            ->groupBy('meal_items.food_id')
            ->orderByRaw('MAX(meal_items.id) DESC')
            ->limit(self::RECENT_LIMIT)
            ->pluck('meal_items.food_id');

        if ($latest->isEmpty()) {
            return new Collection;
        }

        $foods = Food::query()
            ->visibleTo($user)
            ->withCount(['variants' => fn (Builder $v) => $v->visibleTo($user)])
            ->whereIn('id', $latest)
            ->get()
            ->keyBy('id');

        return new Collection($latest->map(fn ($id) => $foods->get((int) $id))->filter()->values()->all());
    }

    /**
     * A private food from a package label. Labels print nutrition per serving or per 100 g;
     * both are accepted and stored per 100 g.
     *
     * @param  array{name: string, name_ar?: ?string, brand?: ?string, category: string, generic_id?: ?int, serving_grams: float|int, serving_label?: ?string, serving_label_ar?: ?string, per: string, calories: float|int, protein: float|int, carbs: float|int, fat: float|int}  $data
     */
    public function createCustom(User $user, array $data): Food
    {
        $factor = $data['per'] === 'serving' ? 100 / (float) $data['serving_grams'] : 1.0;

        $food = new Food([
            'generic_id' => $data['generic_id'] ?? null,
            'category' => $data['category'],
            'name' => trim($data['name']),
            'name_ar' => isset($data['name_ar']) ? trim((string) $data['name_ar']) : null,
            'brand' => isset($data['brand']) ? trim((string) $data['brand']) : null,
            'serving_grams' => (float) $data['serving_grams'],
            'serving_label' => $data['serving_label'] ?? null,
            'serving_label_ar' => $data['serving_label_ar'] ?? null,
            'calories' => round((float) $data['calories'] * $factor, 1),
            'protein' => round((float) $data['protein'] * $factor, 1),
            'carbs' => round((float) $data['carbs'] * $factor, 1),
            'fat' => round((float) $data['fat'] * $factor, 1),
        ]);
        $food->source = FoodSource::User;
        $food->is_verified = false;
        $user->foods()->save($food);

        return $food;
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
