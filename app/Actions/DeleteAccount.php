<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently deletes a user and everything they own (store-policy requirement).
 * Groups they own pass to the longest-standing remaining member; a group with no
 * other members is deleted. Meals, steps, water, weight and memberships cascade.
 */
final class DeleteAccount
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();

            Group::query()->where('owner_id', $user->id)->each(function (Group $group) use ($user): void {
                $heir = GroupMember::query()
                    ->where('group_id', $group->id)
                    ->where('user_id', '!=', $user->id)
                    ->orderBy('joined_at')
                    ->first();

                if ($heir === null) {
                    $group->delete();

                    return;
                }

                $heir->forceFill(['role' => GroupRole::Owner])->save();
                $group->forceFill(['owner_id' => $heir->user_id])->save();
            });

            $user->delete();
        });
    }
}
