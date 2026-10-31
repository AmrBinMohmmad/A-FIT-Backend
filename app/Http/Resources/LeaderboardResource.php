<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Composite payload for GET groups/{group}/leaderboard.
 *
 * @property string $period
 * @property string $from
 * @property string $to
 * @property Collection<int, object> $standings
 * @property int $cache_seconds
 */
class LeaderboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'period' => $this->period,
            'from' => $this->from,
            'to' => $this->to,
            'standings' => StandingResource::collection($this->standings),
            'refresh_after_seconds' => $this->cache_seconds,
        ];
    }
}
