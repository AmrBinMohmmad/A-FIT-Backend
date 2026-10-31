<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One ranked row from StepLeaderboard. Wraps the service's plain object so the wire
 * shape is declared in exactly one place.
 *
 * @property int $user_id
 * @property string $name
 * @property string|null $avatar_url
 * @property string $role
 * @property int $steps
 * @property int $rank
 */
class StandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role,
            'steps' => $this->steps,
            'rank' => $this->rank,
            'is_me' => $this->user_id === $request->user()?->getAuthIdentifier(),
        ];
    }
}
