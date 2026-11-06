<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchFoodsRequest;
use App\Http\Requests\SignedInRequest;
use App\Http\Requests\StoreFoodRequest;
use App\Http\Resources\FoodResource;
use App\Models\Food;
use App\Services\FoodCatalog;
use App\Support\PaginatesResults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FoodController extends Controller
{
    use PaginatesResults;

    /** Search the catalog + the caller's own foods: `q` (name, Arabic name, brand or barcode), `category`. */
    public function index(SearchFoodsRequest $request, FoodCatalog $catalog): JsonResponse
    {
        $page = $catalog->search(
            $request->actor(),
            $request->validated('q'),
            $request->category(),
            $this->perPage($request, 20),
        );

        return $this->success(FoodResource::collection($page)->response()->getData(true));
    }

    /** Foods the caller logged most recently — the picker's empty state. */
    public function recent(SignedInRequest $request, FoodCatalog $catalog): JsonResponse
    {
        return $this->success(FoodResource::collection($catalog->recent($request->actor())));
    }

    /** One food with the variants (kinds, brands, sizes) the caller may see. */
    public function show(SignedInRequest $request, Food $food): JsonResponse
    {
        abort_if(Gate::denies('view', $food), 404, __('errors.record_not_found'));

        $user = $request->actor();
        $food->loadCount(['variants' => fn (Builder $v) => $v->visibleTo($user)]);
        $variants = $food->variants()->visibleTo($user)->orderByDesc('is_verified')->orderBy('name')->get();

        return $this->success([
            'food' => new FoodResource($food),
            'variants' => FoodResource::collection($variants),
        ]);
    }

    public function store(StoreFoodRequest $request, FoodCatalog $catalog): JsonResponse
    {
        /** @var array{name: string, name_ar?: ?string, brand?: ?string, barcode?: ?string, category: string, generic_id?: ?int, serving_grams: float|int, serving_label?: ?string, serving_label_ar?: ?string, per: string, calories: float|int, protein: float|int, carbs: float|int, fat: float|int} $data */
        $data = $request->validated();
        $food = $catalog->createCustom($request->actor(), $data);

        return $this->created(new FoodResource($food), __('messages.food_created'));
    }

    public function destroy(SignedInRequest $request, Food $food): JsonResponse
    {
        abort_if(Gate::denies('delete', $food), 404, __('errors.record_not_found'));

        $food->delete();

        return $this->deleted(__('messages.food_deleted'));
    }
}
