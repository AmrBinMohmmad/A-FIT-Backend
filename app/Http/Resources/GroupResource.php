<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\GroupRole;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Group
 *
 * `my_role` and `members_count` are set by the controller query (withCount + the
 * caller's membership). The invite code is visible to every member so anyone can
 * invite a friend; non-members never receive this resource at all (404 boundary).
 */
class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->getAuthIdentifier();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'invite_code' => $this->invite_code,
            'owner_id' => $this->owner_id,
            'my_role' => $this->owner_id === $userId ? GroupRole::Owner->value : GroupRole::Member->value,
            'members_count' => (int) ($this->memberships_count ?? $this->memberships()->count()),
            'max_members' => $this->max_members,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
