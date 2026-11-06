<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DateRequest;
use App\Http\Requests\MealsSummaryRequest;
use App\Http\Requests\SignedInRequest;
use App\Http\Requests\StoreMealRequest;
use App\Http\Requests\UpdateMealRequest;
use App\Http\Resources\DayTotalsResource;
use App\Http\Resources\FrequentMealResource;
use App\Http\Resources\MealResource;
use App\Models\Meal;
use App\Services\MealComposer;
use App\Services\MealTotals;
use App\Support\PaginatesResults;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MealController extends Controller
{
    use PaginatesResults;

    /** The caller's meals, newest first, optionally for one local day. */
    public function index(DateRequest $request): JsonResponse
    {
        $query = Meal::query()
            ->where('user_id', $request->actor()->id)
            ->when($request->validated('date'), fn ($q, $date) => $q->whereDate('eaten_on', $date))
            ->with('items.food')
            ->orderByDesc('eaten_on')
            ->orderByDesc('id');

        $meals = $query->paginate($this->perPage($request, 50));

        return $this->success(MealResource::collection($meals)->response()->getData(true));
    }

    public function summary(MealsSummaryRequest $request, MealTotals $totals): JsonResponse
    {
        return $this->success(DayTotalsResource::collection($totals->byDay($request->actor(), $request->from(), $request->to())));
    }

    /** Most-logged meals of the last 60 days, for one-tap re-logging. */
    public function frequent(SignedInRequest $request, MealTotals $totals): JsonResponse
    {
        return $this->success(FrequentMealResource::collection($totals->frequent($request->actor(), CarbonImmutable::today())));
    }

    /** Typed macros, or composed from catalog `items` (totals computed server-side). */
    public function store(StoreMealRequest $request, MealComposer $composer): JsonResponse
    {
        $meal = $composer->save($request->actor(), new Meal, $request->mealAttributes(), $request->items());

        return $this->created(new MealResource($meal), __('messages.meal_created'));
    }

    public function update(UpdateMealRequest $request, Meal $meal, MealComposer $composer): JsonResponse
    {
        abort_if(Gate::denies('update', $meal), 404, __('errors.record_not_found'));

        $meal = $composer->save($request->actor(), $meal, $request->mealAttributes(), $request->items());

        return $this->success(new MealResource($meal), __('messages.meal_updated'));
    }

    public function destroy(SignedInRequest $request, Meal $meal): JsonResponse
    {
        abort_if(Gate::denies('delete', $meal), 404, __('errors.record_not_found'));

        $meal->delete();

        return $this->deleted(__('messages.meal_deleted'));
    }
}
