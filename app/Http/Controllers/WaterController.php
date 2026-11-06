<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveWaterRequest;
use App\Http\Requests\SignedInRequest;
use App\Http\Resources\WaterEntryResource;
use App\Models\WaterEntry;
use Illuminate\Http\JsonResponse;

class WaterController extends Controller
{
    public function show(SignedInRequest $request, string $date): JsonResponse
    {
        $this->assertDate($date);

        $entry = WaterEntry::query()->where('user_id', $request->actor()->id)->whereDate('date', $date)->first();

        return $this->success($entry ? new WaterEntryResource($entry) : ['date' => $date, 'ml' => 0]);
    }

    /** Absolute total for the day (the client adds its glass and sends the new total). */
    public function update(SaveWaterRequest $request, string $date): JsonResponse
    {
        $this->assertDate($date);

        $entry = $request->actor()->waterEntries()->updateOrCreate(
            ['date' => $date],
            ['ml' => (int) $request->validated('ml')],
        );

        return $this->success(new WaterEntryResource($entry), __('messages.water_saved'));
    }

    private function assertDate(string $date): void
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && strtotime($date) !== false, 404, __('errors.resource_not_found'));
    }
}
