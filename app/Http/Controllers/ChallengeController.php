<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SignedInRequest;
use App\Http\Requests\StoreChallengeRequest;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\Group;
use App\Services\StepLeaderboard;
use App\Support\PaginatesResults;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ChallengeController extends Controller
{
    use PaginatesResults;

    public function index(SignedInRequest $request, Group $group): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));

        $challenges = $group->challenges()
            ->orderByDesc('ends_on')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 20));

        return $this->success(ChallengeResource::collection($challenges)->response()->getData(true));
    }

    public function store(StoreChallengeRequest $request, Group $group): JsonResponse
    {
        abort_if(Gate::denies('view', $group), 404, __('errors.record_not_found'));
        $this->authorize('create', [Challenge::class, $group]);

        $challenge = new Challenge($request->validated());
        $challenge->group_id = $group->id;
        $challenge->creator_id = $request->actor()->id;
        $challenge->save();

        return $this->created(new ChallengeResource($challenge), __('messages.challenge_created'));
    }

    public function show(SignedInRequest $request, Challenge $challenge, StepLeaderboard $leaderboard): JsonResponse
    {
        abort_if(Gate::denies('view', $challenge), 404, __('errors.record_not_found'));

        $challenge->setRelation('standings', $leaderboard->standings($challenge->group, $challenge->starts_on, $challenge->ends_on));

        return $this->success(new ChallengeResource($challenge));
    }

    public function destroy(SignedInRequest $request, Challenge $challenge): JsonResponse
    {
        abort_if(Gate::denies('view', $challenge), 404, __('errors.record_not_found'));
        $this->authorize('delete', $challenge);

        $challenge->delete();

        return $this->deleted(__('messages.challenge_deleted'));
    }
}
