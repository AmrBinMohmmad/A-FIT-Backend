<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GroupRole;
use App\Enums\LeaderboardPeriod;
use App\Models\Group;
use App\Models\GroupMember;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ranks a group's members by steps inside a date window with ONE aggregate query,
 * and caches each (group, window) for 60 seconds so a group of 50 friends refreshing
 * the leaderboard together costs the database one query a minute, not fifty.
 */
final class StepLeaderboard
{
    public const CACHE_SECONDS = 60;

    public function __construct(private readonly Cache $cache) {}

    /**
     * @return Collection<int, object{user_id: int, name: string, avatar_url: string|null, role: string, steps: int, rank: int}>
     */
    public function forPeriod(Group $group, LeaderboardPeriod $period, CarbonImmutable $anchor): Collection
    {
        [$from, $to] = $period->bounds($anchor);

        $key = sprintf('group:%d:lb:%s:%s', $group->id, $period->value, $anchor->toDateString());

        // The cache store holds plain arrays only: config('cache.serializable_classes') is
        // false, so a cached Collection of objects would come back as __PHP_Incomplete_Class.
        /** @var list<array{user_id: int, name: string, avatar_url: string|null, role: string, steps: int, rank: int}> $rows */
        $rows = $this->cache->remember(
            $key,
            self::CACHE_SECONDS,
            fn () => $this->standings($group, $from, $to)->map(fn (object $row) => (array) $row)->all(),
        );

        return collect($rows)->map(fn (array $row) => (object) $row);
    }

    /**
     * Uncached standings for an arbitrary window (challenges use this with their own
     * window; the challenge screen is polled far less than the leaderboard).
     *
     * @return Collection<int, object{user_id: int, name: string, avatar_url: string|null, role: string, steps: int, rank: int}>
     */
    public function standings(Group $group, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = GroupMember::query()
            ->where('group_members.group_id', $group->id)
            ->join('users', 'users.id', '=', 'group_members.user_id')
            ->leftJoin('step_entries', function ($join) use ($from, $to) {
                $join->on('step_entries.user_id', '=', 'group_members.user_id')
                    ->whereBetween('step_entries.date', [$from->toDateString(), $to->toDateString()]);
            })
            ->groupBy('group_members.user_id', 'users.name', 'users.avatar_path', 'group_members.role')
            ->orderByDesc('steps')
            ->orderBy('users.name')
            ->get([
                'group_members.user_id',
                'users.name',
                'users.avatar_path',
                'group_members.role',
                DB::raw('COALESCE(SUM(step_entries.steps), 0) as steps'),
            ]);

        $rank = 0;
        $previous = null;

        return $rows->values()->map(function ($row, int $index) use (&$rank, &$previous) {
            $steps = (int) $row->steps;
            // Competition ranking: equal step counts share a rank (1, 1, 3).
            if ($steps !== $previous) {
                $rank = $index + 1;
                $previous = $steps;
            }

            return (object) [
                'user_id' => (int) $row->user_id,
                'name' => (string) $row->name,
                'avatar_url' => Photos::urlFor(is_string($row->avatar_path) ? $row->avatar_path : null),
                'role' => $row->role instanceof GroupRole ? $row->role->value : (string) $row->role,
                'steps' => $steps,
                'rank' => $rank,
            ];
        });
    }

    /** Drops every cached window for the group (owner edits, member joins/leaves). */
    public function forget(Group $group, CarbonImmutable $anchor): void
    {
        foreach (LeaderboardPeriod::cases() as $period) {
            $this->cache->forget(sprintf('group:%d:lb:%s:%s', $group->id, $period->value, $anchor->toDateString()));
        }
    }
}
