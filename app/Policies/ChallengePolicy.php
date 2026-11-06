<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Challenge;
use App\Models\Group;
use App\Models\User;

class ChallengePolicy
{
    public function view(User $user, Challenge $challenge): bool
    {
        return $challenge->group->memberships()->where('user_id', $user->id)->exists();
    }

    /** Any member may propose a challenge for their group. */
    public function create(User $user, Group $group): bool
    {
        return $group->memberships()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Challenge $challenge): bool
    {
        return $challenge->creator_id === $user->id || $challenge->group->owner_id === $user->id;
    }
}
