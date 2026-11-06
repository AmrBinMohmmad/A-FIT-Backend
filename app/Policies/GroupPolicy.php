<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    /** Membership is the whole boundary: a non-member learns nothing about the group. */
    public function view(User $user, Group $group): bool
    {
        return $group->memberships()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group);
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group);
    }

    public function manageMembers(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group);
    }

    public function leave(User $user, Group $group): bool
    {
        return $this->view($user, $group) && ! $this->isOwner($user, $group);
    }

    private function isOwner(User $user, Group $group): bool
    {
        return $group->owner_id === $user->id
            && $group->memberships()->where('user_id', $user->id)->where('role', GroupRole::Owner->value)->exists();
    }
}
