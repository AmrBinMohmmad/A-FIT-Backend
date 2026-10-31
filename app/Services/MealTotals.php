<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MealType;
use App\Models\Meal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Per-day calorie and macro totals over a bounded window, one GROUP BY query. */
final class MealTotals
{
    /**
     * The user's most-logged meal names over the last 60 days with their typical macros,
     * so a repeat meal is one tap. One GROUP BY query, capped at 8 rows.
     *
     * @return Collection<int, object>
     */
    public function frequent(User $user, CarbonImmutable $today, int $limit = 8): Collection
    {
        return Meal::query()
            ->where('user_id', $user->id)
            ->whereBetween('eaten_on', [$today->subDays(60)->toDateString(), $today->toDateString()])
            ->groupBy('name', 'meal_type')
            ->orderByDesc('times')
            ->orderByDesc('last_eaten')
            ->limit($limit)
            ->get([
                'name',
                'meal_type',
                DB::raw('COUNT(*) as times'),
                DB::raw('MAX(eaten_on) as last_eaten'),
                DB::raw('ROUND(AVG(calories)) as calories'),
                DB::raw('ROUND(AVG(COALESCE(protein, 0))) as protein'),
                DB::raw('ROUND(AVG(COALESCE(carbs, 0))) as carbs'),
                DB::raw('ROUND(AVG(COALESCE(fat, 0))) as fat'),
            ])
            ->map(fn ($row) => (object) [
                'name' => (string) $row->name,
                'meal_type' => $row->meal_type instanceof MealType ? $row->meal_type->value : (string) $row->meal_type,
                'times' => (int) $row->times,
                'calories' => (int) $row->calories,
                'protein' => (int) $row->protein,
                'carbs' => (int) $row->carbs,
                'fat' => (int) $row->fat,
            ]);
    }

    /** @return Collection<int, object> */
    public function byDay(User $user, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Meal::query()
            ->where('user_id', $user->id)
            ->whereBetween('eaten_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('eaten_on')
            ->orderBy('eaten_on')
            ->get([
                'eaten_on',
                DB::raw('COUNT(*) as meals_count'),
                DB::raw('COALESCE(SUM(calories), 0) as calories'),
                DB::raw('COALESCE(SUM(protein), 0) as protein'),
                DB::raw('COALESCE(SUM(carbs), 0) as carbs'),
                DB::raw('COALESCE(SUM(fat), 0) as fat'),
            ])
            ->map(fn ($row) => (object) [
                'eaten_on' => CarbonImmutable::parse((string) $row->eaten_on)->toDateString(),
                'meals_count' => (int) $row->meals_count,
                'calories' => (int) $row->calories,
                'protein' => (int) $row->protein,
                'carbs' => (int) $row->carbs,
                'fat' => (int) $row->fat,
            ]);
    }
}
