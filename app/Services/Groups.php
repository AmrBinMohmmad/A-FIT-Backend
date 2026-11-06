<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Group membership rules in one place: creation, joining by code, leaving, removal and
 * code rotation. State-machine conflicts answer 409; an unknown code is
 * a 404 so a guesser learns nothing beyond "no".
 */
final class Groups
{
    public function __construct(private readonly StepLeaderboard $leaderboard) {}

    public function create(User $owner, string $name): Group
    {
        return DB::transaction(function () use ($owner, $name): Group {
            $group = new Group(['name' => $name]);
            $group->owner_id = $owner->id;
            $group->invite_code = Group::freshInviteCode();
            $group->save();

            $this->attach($group, $owner, GroupRole::Owner);

            return $group;
        });
    }

    public function join(User $user, string $code): Group
    {
        $group = Group::query()->where('invite_code', $code)->withCount('memberships')->first();

        if ($group === null) {
            throw new NotFoundHttpException(__('errors.group_code_invalid'));
        }

        if ($group->memberships()->where('user_id', $user->id)->exists()) {
            throw new ConflictHttpException(__('errors.already_member'));
        }

        if ((int) $group->memberships_count >= $group->max_members) {
            throw new ConflictHttpException(__('errors.group_full'));
        }

        $this->attach($group, $user, GroupRole::Member);
        $this->leaderboard->forget($group, CarbonImmutable::today());

        return $group;
    }

    public function leave(User $user, Group $group): void
    {
        if ($group->owner_id === $user->id) {
            throw new ConflictHttpException(__('errors.owner_cannot_leave'));
        }

        GroupMember::query()->where('group_id', $group->id)->where('user_id', $user->id)->delete();
        $this->leaderboard->forget($group, CarbonImmutable::today());
    }

    public function remove(Group $group, User $member): void
    {
        if ($group->owner_id === $member->id) {
            throw new ConflictHttpException(__('errors.owner_cannot_leave'));
        }

        GroupMember::query()->where('group_id', $group->id)->where('user_id', $member->id)->delete();
        $this->leaderboard->forget($group, CarbonImmutable::today());
    }

    public function rotateCode(Group $group): Group
    {
        $group->invite_code = Group::freshInviteCode();
        $group->save();

        return $group;
    }

    private function attach(Group $group, User $user, GroupRole $role): void
    {
        $membership = new GroupMember;
        $membership->group_id = $group->id;
        $membership->user_id = $user->id;
        $membership->role = $role;
        $membership->joined_at = now();
        $membership->save();
    }
}
