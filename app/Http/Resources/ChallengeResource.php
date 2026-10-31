<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ChallengeStatus;
use App\Models\Challenge;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Challenge
 *
 * The controller attaches ranked standings via setRelation('standings', …) for the detail
 * view only; list views omit the key.
 */
class ChallengeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = CarbonImmutable::today();
        $standings = $this->relationLoaded('standings') ? $this->getRelation('standings') : null;

        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'creator_id' => $this->creator_id,
            'title' => $this->title,
            'type' => $this->type->value,
            'goal_steps' => $this->goal_steps,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'status' => $this->statusOn($today)->value,
            'days_left' => max(0, $today->diffInDays($this->ends_on, false) + 1),
            'standings' => $this->when($standings !== null, fn () => StandingResource::collection($standings)),
            'winner_user_id' => $this->when($standings !== null, fn () => $this->winnerId($standings, $today)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @param Collection<int, object> $standings */
    private function winnerId($standings, CarbonImmutable $today): ?int
    {
        if ($this->statusOn($today) !== ChallengeStatus::Ended) {
            return null;
        }
        $top = $standings->first();
        if ($top === null || $top->steps === 0) {
            return null;
        }
        if ($this->goal_steps !== null && $top->steps < $this->goal_steps) {
            return null;
        }

        return $top->user_id;
    }
}
