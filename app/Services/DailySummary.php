<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaderboardPeriod;
use App\Models\Challenge;
use App\Models\Group;
use App\Models\Meal;
use App\Models\StepEntry;
use App\Models\User;
use App\Models\WaterEntry;
use App\Models\WeightEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Everything the home screen needs for one calendar day, gathered server-side so the
 * client makes ONE request instead of six. Each section is a bounded, indexed query.
 */
final class DailySummary
{
    public function __construct(private readonly StepLeaderboard $leaderboard) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, CarbonImmutable $date): array
    {
        $profile = $user->profile;

        $stepEntry = StepEntry::query()->where('user_id', $user->id)->whereDate('date', $date->toDateString())->first();

        $stepsGoal = $profile?->daily_steps_goal ?? 10000;

        // Last 60 days of step totals: feeds the 7-day strip and the goal streak in ONE query.
        $recent = StepEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$date->subDays(59)->toDateString(), $date->toDateString()])
            ->orderBy('date')
            ->get(['date', 'steps'])
            ->keyBy(fn (StepEntry $e) => $e->date->toDateString());

        $week = [];
        for ($i = 6; $i >= 0; $i--) {
            $key = $date->subDays($i)->toDateString();
            $week[] = ['date' => $key, 'steps' => (int) ($recent->get($key)?->steps ?? 0)];
        }

        // Streak: consecutive days on goal ending today (today counts only once it is reached).
        $streak = 0;
        for ($cursor = $date; $cursor->gte($date->subDays(59)); $cursor = $cursor->subDay()) {
            $steps = (int) ($recent->get($cursor->toDateString())?->steps ?? 0);
            if ($steps >= $stepsGoal) {
                $streak++;
            } elseif (! $cursor->equalTo($date)) {
                break;
            }
        }

        $mealTotals = Meal::query()
            ->where('user_id', $user->id)
            ->whereDate('eaten_on', $date->toDateString())
            ->first([
                DB::raw('COUNT(*) as meals_count'),
                DB::raw('COALESCE(SUM(calories), 0) as calories'),
                DB::raw('COALESCE(SUM(protein), 0) as protein'),
                DB::raw('COALESCE(SUM(carbs), 0) as carbs'),
                DB::raw('COALESCE(SUM(fat), 0) as fat'),
            ]);

        $water = WaterEntry::query()->where('user_id', $user->id)->whereDate('date', $date->toDateString())->first();

        $latestWeight = WeightEntry::query()->where('user_id', $user->id)->orderByDesc('date')->first();

        $primaryGroup = Group::query()
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id))
            ->withCount('memberships')
            ->orderBy('id')
            ->first();

        $groupSummary = null;
        if ($primaryGroup !== null) {
            $standings = $this->leaderboard->forPeriod($primaryGroup, LeaderboardPeriod::Today, $date);
            $me = $standings->firstWhere('user_id', $user->id);
            $leader = $standings->first();
            $groupSummary = [
                'id' => $primaryGroup->id,
                'name' => $primaryGroup->name,
                'members_count' => (int) $primaryGroup->memberships_count,
                'my_rank' => $me?->rank,
                'leader' => $leader ? ['user_id' => $leader->user_id, 'name' => $leader->name, 'avatar' => $leader->avatar, 'steps' => $leader->steps] : null,
                'others' => $standings
                    ->where('user_id', '!=', $user->id)
                    ->take(3)
                    ->map(fn ($row) => ['user_id' => $row->user_id, 'name' => $row->name, 'avatar' => $row->avatar, 'steps' => $row->steps])
                    ->values()
                    ->all(),
            ];
        }

        $challenge = Challenge::query()
            ->whereIn('group_id', fn ($q) => $q->select('group_id')->from('group_members')->where('user_id', $user->id))
            ->whereDate('ends_on', '>=', $date->toDateString())
            ->orderBy('ends_on')
            ->first();

        $challengeSummary = null;
        if ($challenge !== null) {
            $teamSteps = (int) StepEntry::query()
                ->whereIn('user_id', fn ($q) => $q->select('user_id')->from('group_members')->where('group_id', $challenge->group_id))
                ->whereBetween('date', [$challenge->starts_on->toDateString(), $challenge->ends_on->toDateString()])
                ->sum('steps');

            $challengeSummary = [
                'id' => $challenge->id,
                'group_id' => $challenge->group_id,
                'title' => $challenge->title,
                'type' => $challenge->type->value,
                'status' => $challenge->statusOn($date)->value,
                'goal_steps' => $challenge->goal_steps,
                'team_steps' => $teamSteps,
                'starts_on' => $challenge->starts_on->toDateString(),
                'ends_on' => $challenge->ends_on->toDateString(),
                'days_left' => max(0, $date->diffInDays($challenge->ends_on, false) + 1),
            ];
        }

        return [
            'date' => $date->toDateString(),
            'steps' => [
                'steps' => $stepEntry?->steps ?? 0,
                'goal' => $stepsGoal,
                'source' => $stepEntry?->source?->value,
                'synced_at' => $stepEntry?->synced_at?->toIso8601String(),
                'streak' => $streak,
                'week' => $week,
            ],
            'nutrition' => [
                'meals_count' => (int) ($mealTotals->meals_count ?? 0),
                'calories' => (int) ($mealTotals->calories ?? 0),
                'protein' => (int) ($mealTotals->protein ?? 0),
                'carbs' => (int) ($mealTotals->carbs ?? 0),
                'fat' => (int) ($mealTotals->fat ?? 0),
                'targets' => $profile ? [
                    'calories' => $profile->daily_calories,
                    'protein' => $profile->daily_protein,
                    'carbs' => $profile->daily_carbs,
                    'fat' => $profile->daily_fat,
                ] : null,
            ],
            'water' => [
                'ml' => $water?->ml ?? 0,
                'goal_ml' => $profile?->water_goal_ml ?? 2500,
            ],
            'weight' => $latestWeight ? [
                'date' => $latestWeight->date->toDateString(),
                'weight_kg' => (float) $latestWeight->weight_kg,
            ] : null,
            'group' => $groupSummary,
            'challenge' => $challengeSummary,
            'has_profile' => $profile !== null,
        ];
    }
}
