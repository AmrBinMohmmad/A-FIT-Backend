<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Food;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds a meal out of catalog portions. Totals are ALWAYS computed here from the foods'
 * per-100 g values — numbers a client sends alongside items are ignored — and every line
 * keeps a snapshot of what was eaten, so later catalog edits never rewrite history.
 */
final class MealComposer
{
    /**
     * @param  array<string, mixed>  $attributes  validated meal fields (name, meal_type, eaten_on, ...)
     * @param  list<array{food_id: int, grams?: float|int|null, servings?: float|int|null}>|null  $items
     */
    public function save(User $user, Meal $meal, array $attributes, ?array $items): Meal
    {
        return DB::transaction(function () use ($user, $meal, $attributes, $items): Meal {
            $lines = $items === null ? null : $this->lines($user, $items);

            if ($lines !== null) {
                $totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];
                foreach ($lines as $line) {
                    foreach ($totals as $key => $_) {
                        $totals[$key] += $line[$key];
                    }
                }
                $attributes = array_merge($attributes, $totals);
                $attributes['name'] = $this->nameFor($attributes['name'] ?? null, $lines);
            }

            $meal->fill($attributes);
            $meal->user_id = $user->id;
            $meal->save();

            if ($lines !== null) {
                $meal->items()->delete();
                $meal->items()->createMany($lines);
            }

            return $meal->load('items');
        });
    }

    /**
     * @param  list<array{food_id: int, grams?: float|int|null, servings?: float|int|null}>  $items
     * @return list<array<string, mixed>>
     */
    private function lines(User $user, array $items): array
    {
        $foods = Food::query()
            ->visibleTo($user)
            ->whereIn('id', array_map(fn (array $i) => (int) $i['food_id'], $items))
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($items as $position => $item) {
            /** @var Food $food */
            $food = $foods->get((int) $item['food_id']);

            $servings = isset($item['servings']) ? (float) $item['servings'] : null;
            $grams = isset($item['grams']) && $item['grams'] !== null
                ? (float) $item['grams']
                : round(($servings ?? 1.0) * $food->serving_grams, 1);
            if ($servings === null && $food->serving_grams > 0) {
                $servings = round($grams / $food->serving_grams, 2);
            }

            $lines[] = [
                'food_id' => $food->id,
                'name' => $food->name,
                'brand' => $food->brand,
                'grams' => $grams,
                'servings' => $servings,
                'position' => $position,
                ...$food->portion($grams),
            ];
        }

        return $lines;
    }

    /**
     * A name when the client gave none: the first three item names, "Egg, Toast, Labneh".
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function nameFor(?string $given, array $lines): string
    {
        $given = trim((string) $given);
        if ($given !== '') {
            return $given;
        }

        $names = array_values(array_unique(array_map(fn (array $l) => (string) $l['name'], $lines)));
        $name = implode(', ', array_slice($names, 0, 3));
        if (count($names) > 3) {
            $name .= ' +'.(count($names) - 3);
        }

        return mb_substr($name, 0, 80);
    }
}
