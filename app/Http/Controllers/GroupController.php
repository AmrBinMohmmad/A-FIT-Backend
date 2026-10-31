<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LeaderboardPeriod;
use App\Http\Requests\JoinGroupRequest;
use App\Http\Requests\LeaderboardRequest;
use App\Http\Requests\SignedInRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UploadPhotoRequest;
use App\Http\Resources\GroupResource;
use App\Http\Resources\LeaderboardResource;
use App\Http\Resources\StandingResource;
use App\Models\Group;
use App\Models\User;
use App\Services\Groups;
use App\Services\Photos;
use App\Services\StepLeaderboard;
use App\Support\PaginatesResults;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Membership is the visibility boundary: a non-member gets 404 on every group route
 * (never 403 — the group's existence is not disclosed, Rakaya D10.16).
 */
class GroupController extends Controller
{
    use PaginatesResults;

    public function index(SignedInRequest $request): JsonResponse
    {
        $groups = Group::query()
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $request->actor()->id))
            ->withCount('memberships')
            ->orderBy('name')
            ->paginate($this->perPage($request, 20));

        return $this->success(GroupResource::collection($groups)->response()->getData(true));
    }

    public function store(StoreGroupRequest $request, Groups $groups): JsonResponse
    {
        $group = $groups->create($request->actor(), (string) $request->validated('name'));
        $group->loadCount('memberships');

        return $this->created(new GroupResource($group), __('messages.group_created'));
    }

    /** The group with today's standings — one screen, one request. */
    public function show(SignedInRequest $request, Group $group, StepLeaderboard $leaderboard): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));

        $group->loadCount('memberships');
        $standings = $leaderboard->forPeriod($group, LeaderboardPeriod::Today, CarbonImmutable::today());

        return $this->success([
            'group' => new GroupResource($group),
            'today' => StandingResource::collection($standings),
        ]);
    }

    public function update(StoreGroupRequest $request, Group $group): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));
        $this->authorize('update', $group);

        $group->fill($request->validated())->save();
        $group->loadCount('memberships');

        return $this->success(new GroupResource($group), __('messages.group_updated'));
    }

    public function updatePhoto(UploadPhotoRequest $request, Group $group, Photos $photos): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));
        $this->authorize('update', $group);

        $path = $photos->replace($request->photo(), Photos::GROUPS_DIR, $group->photo_path);
        $group->forceFill(['photo_path' => $path])->save();
        $group->loadCount('memberships');

        return $this->success(new GroupResource($group), __('messages.photo_saved'));
    }

    public function destroyPhoto(SignedInRequest $request, Group $group, Photos $photos): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));
        $this->authorize('update', $group);

        $photos->remove($group->photo_path);
        $group->forceFill(['photo_path' => null])->save();
        $group->loadCount('memberships');

        return $this->success(new GroupResource($group), __('messages.photo_removed'));
    }

    public function destroy(SignedInRequest $request, Group $group): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));
        $this->authorize('delete', $group);

        $group->delete();

        return $this->deleted(__('messages.group_deleted'));
    }

    public function join(JoinGroupRequest $request, Groups $groups): JsonResponse
    {
        $group = $groups->join($request->actor(), (string) $request->validated('code'));
        $group->loadCount('memberships');

        return $this->success(new GroupResource($group), __('messages.group_joined'));
    }

    public function leave(SignedInRequest $request, Group $group, Groups $groups): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));

        $groups->leave($request->actor(), $group);

        return $this->success(null, __('messages.group_left'));
    }

    public function rotateCode(SignedInRequest $request, Group $group, Groups $groups): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));
        $this->authorize('update', $group);

        $group = $groups->rotateCode($group);
        $group->loadCount('memberships');

        return $this->success(new GroupResource($group), __('messages.code_rotated'));
    }

    public function removeMember(SignedInRequest $request, Group $group, User $user, Groups $groups): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));
        $this->authorize('manageMembers', $group);

        $groups->remove($group, $user);

        return $this->success(null, __('messages.member_removed'));
    }

    public function leaderboard(LeaderboardRequest $request, Group $group, StepLeaderboard $leaderboard): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));

        $period = $request->period();
        [$from, $to] = $period->bounds($request->day());

        return $this->success(new LeaderboardResource((object) [
            'period' => $period->value,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'standings' => $leaderboard->forPeriod($group, $period, $request->day()),
            'cache_seconds' => StepLeaderboard::CACHE_SECONDS,
        ]));
    }
}
