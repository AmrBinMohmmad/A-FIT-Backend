<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DateRequest;
use App\Http\Resources\DashboardResource;
use App\Services\DailySummary;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /** One request for the whole home screen. */
    public function show(DateRequest $request, DailySummary $summary): JsonResponse
    {
        return $this->success(new DashboardResource($summary->forUser($request->actor(), $request->day())));
    }
}
