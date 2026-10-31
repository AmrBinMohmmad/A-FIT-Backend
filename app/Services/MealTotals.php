<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Meal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Per-day calorie and macro totals over a bounded window, one GROUP BY query. */
final class MealTotals
{
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
