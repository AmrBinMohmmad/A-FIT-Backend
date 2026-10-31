<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SignedInRequest;
use App\Http\Requests\StoreWeightRequest;
use App\Http\Requests\WeightHistoryRequest;
use App\Http\Resources\WeightEntryResource;
use App\Models\WeightEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WeightController extends Controller
{
    /** Bounded history (≤ 366 days), oldest first for the chart. */
    public function history(WeightHistoryRequest $request): JsonResponse
    {
        $entries = WeightEntry::query()
            ->where('user_id', $request->actor()->id)
            ->whereBetween('date', [$request->from()->toDateString(), $request->to()->toDateString()])
            ->orderBy('date')
            ->get();

        return $this->success(WeightEntryResource::collection($entries));
    }

    /** One weight per day; saving the same day again replaces it. */
    public function store(StoreWeightRequest $request): JsonResponse
    {
        $entry = $request->actor()->weightEntries()->updateOrCreate(
            ['date' => $request->validated('date')],
            ['weight_kg' => $request->validated('weight_kg')],
        );

        return $this->success(new WeightEntryResource($entry), __('messages.weight_saved'));
    }

    public function destroy(SignedInRequest $request, WeightEntry $weightEntry): JsonResponse
    {
        abort_if(Gate::denies('delete', $weightEntry), 404, __('errors.record_not_found'));

        $weightEntry->delete();

        return $this->deleted(__('messages.weight_deleted'));
    }
}
