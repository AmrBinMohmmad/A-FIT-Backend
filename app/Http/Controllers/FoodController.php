<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportFoodRequest;
use App\Http\Requests\LookupFoodsRequest;
use App\Http\Requests\SearchFoodsRequest;
use App\Http\Requests\SignedInRequest;
use App\Http\Requests\StoreFoodRequest;
use App\Http\Resources\FoodResource;
use App\Http\Resources\LookupResultResource;
use App\Models\Food;
use App\Services\FoodCatalog;
use App\Services\OpenFoodFacts;
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

    /** Online products (Open Food Facts) matching a name — opt-in, cached a day per query. */
    public function lookup(LookupFoodsRequest $request, OpenFoodFacts $off): JsonResponse
    {
        $found = $off->search((string) $request->validated('q'));

        return $this->success([
            'available' => $found['available'],
            'results' => LookupResultResource::collection($this->withCatalogIds($found['products'])),
        ]);
    }

    /** One online product by barcode — for a scan the catalog does not know. */
    public function lookupBarcode(SignedInRequest $request, string $barcode, OpenFoodFacts $off): JsonResponse
    {
        abort_unless(preg_match('/^\d{8,14}$/', $barcode) === 1, 404, __('errors.product_not_found'));
        $found = $off->product($barcode);

        return $this->success([
            'available' => $found['available'],
            'result' => $found['product'] === null ? null : new LookupResultResource($this->withCatalogIds([$found['product']])[0]),
        ]);
    }

    /** Files an online product in the shared catalog (idempotent) so it can be logged and rescanned. */
    public function import(ImportFoodRequest $request, OpenFoodFacts $off): JsonResponse
    {
        $existing = Food::query()->whereNull('user_id')->where('barcode', $request->barcode())->first();
        if ($existing !== null) {
            return $this->success(new FoodResource($existing));
        }

        $found = $off->product($request->barcode());
        abort_if($found['product'] === null, $found['available'] ? 404 : 503, $found['available'] ? __('errors.product_not_found') : __('errors.online_catalog_down'));

        return $this->created(new FoodResource($off->store($found['product'])), __('messages.food_imported'));
    }

    /**
     * Marks candidates already in the shared catalog with their id (one query for the page).
     *
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function withCatalogIds(array $products): array
    {
        $ids = Food::query()->whereNull('user_id')
            ->whereIn('barcode', array_column($products, 'barcode'))
            ->pluck('id', 'barcode');

        return array_map(fn (array $p) => $p + ['food_id' => $ids->get($p['barcode'])], $products);
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
