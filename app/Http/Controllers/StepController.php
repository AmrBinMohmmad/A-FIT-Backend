<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StepsHistoryRequest;
use App\Http\Requests\SyncStepsRequest;
use App\Http\Resources\StepEntryResource;
use App\Models\StepEntry;
use Illuminate\Http\JsonResponse;

class StepController extends Controller
{
    /**
     * Idempotent batch upsert of per-day totals — one statement for the whole batch.
     * The client calls this at most every few minutes (throttle:steps-sync caps it).
     */
    public function sync(SyncStepsRequest $request): JsonResponse
    {
        $user = $request->actor();
        $now = now();

        /** @var list<array{date: string, steps: int, source: string}> $entries */
        $entries = $request->validated('entries');

        $rows = [];
        foreach ($entries as $entry) {
            $rows[$entry['date']] = [
                'user_id' => $user->id,
                'date' => $entry['date'],
                'steps' => (int) $entry['steps'],
                'source' => $entry['source'],
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        StepEntry::query()->upsert(array_values($rows), ['user_id', 'date'], ['steps', 'source', 'synced_at', 'updated_at']);

        $saved = StepEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('date', array_keys($rows))
            ->orderBy('date')
            ->get();

        return $this->success([
            'entries' => StepEntryResource::collection($saved),
            'goal' => $user->profile?->daily_steps_goal ?? 10000,
        ], __('messages.steps_synced'));
    }

    /** Bounded history (≤ 90 days) for the charts; one row per day that has data. */
    public function history(StepsHistoryRequest $request): JsonResponse
    {
        $entries = StepEntry::query()
            ->where('user_id', $request->actor()->id)
            ->whereBetween('date', [$request->from()->toDateString(), $request->to()->toDateString()])
            ->orderBy('date')
            ->get();

        return $this->success([
            'from' => $request->from()->toDateString(),
            'to' => $request->to()->toDateString(),
            'goal' => $request->actor()->profile?->daily_steps_goal ?? 10000,
            'entries' => StepEntryResource::collection($entries),
        ]);
    }
}
