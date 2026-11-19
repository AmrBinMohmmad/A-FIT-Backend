<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FoodCategory;
use App\Enums\FoodSource;
use App\Models\Food;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Live product lookup against Open Food Facts (free, ODbL — the app credits it): names,
 * brands, photos, barcodes and per-100 g nutrition for shelf products. Every call goes
 * through the cache so a popular query or barcode costs their API one request a day, and
 * an outage degrades to "online catalog unavailable" instead of an error.
 */
final class OpenFoodFacts
{
    public const BASE = 'https://world.openfoodfacts.org';

    public const FIELDS = 'code,product_name,product_name_en,product_name_ar,brands,serving_size,nutriments,image_front_small_url,image_front_url,categories_tags,countries_tags';

    public const SEARCH_TTL = 86400;

    public const PRODUCT_TTL = 604800;

    public const MISS_TTL = 3600;

    public const DOWN_TTL = 60;

    /** OFF category tag → [our aisle, generic catalog_key]. Shared with the bulk importer. */
    public const CATEGORIES = [
        'chicken-eggs' => [FoodCategory::Eggs, 'egg'],
        'eggs' => [FoodCategory::Eggs, 'egg'],
        'sandwich-breads' => [FoodCategory::Bread, 'toast'],
        'pita-breads' => [FoodCategory::Bread, 'pita'],
        'breads' => [FoodCategory::Bread, null],
        'milks' => [FoodCategory::Dairy, 'milk-full'],
        'yogurts' => [FoodCategory::Dairy, 'yogurt-plain'],
        'labneh' => [FoodCategory::Dairy, 'labneh'],
        'cheeses' => [FoodCategory::Dairy, 'cheese-cheddar'],
        'dairies' => [FoodCategory::Dairy, null],
        'dates' => [FoodCategory::Fruits, 'dates'],
        'fruits' => [FoodCategory::Fruits, null],
        'vegetables' => [FoodCategory::Vegetables, null],
        'hummus' => [FoodCategory::Legumes, 'hummus'],
        'legumes' => [FoodCategory::Legumes, null],
        'canned-tunas' => [FoodCategory::Seafood, 'tuna-canned-water'],
        'seafood' => [FoodCategory::Seafood, null],
        'poultries' => [FoodCategory::Poultry, null],
        'meats' => [FoodCategory::Meat, null],
        'breakfast-cereals' => [FoodCategory::Grains, 'cornflakes'],
        'cereals-and-potatoes' => [FoodCategory::Grains, null],
        'pastas' => [FoodCategory::Grains, 'pasta'],
        'rices' => [FoodCategory::Grains, 'rice-white'],
        'nuts' => [FoodCategory::NutsSeeds, null],
        'fats' => [FoodCategory::FatsOils, null],
        'fruit-juices' => [FoodCategory::Beverages, 'orange-juice'],
        'sodas' => [FoodCategory::Beverages, 'cola'],
        'beverages' => [FoodCategory::Beverages, null],
        'chocolates' => [FoodCategory::Sweets, 'chocolate-milk'],
        'sweet-snacks' => [FoodCategory::Sweets, null],
        'desserts' => [FoodCategory::Sweets, null],
        'crisps' => [FoodCategory::Snacks, 'potato-chips'],
        'salty-snacks' => [FoodCategory::Snacks, null],
        'snacks' => [FoodCategory::Snacks, null],
        'meals' => [FoodCategory::Dishes, null],
    ];

    public function __construct(private readonly Cache $cache) {}

    /**
     * Free-text search, most-scanned products first, Saudi-sold ones ahead of the rest.
     *
     * @return array{available: bool, products: list<array<string, mixed>>}
     */
    public function search(string $query, int $limit = 20): array
    {
        $query = mb_strtolower(trim($query));
        $key = 'off:q:'.sha1($query.'|'.$limit);

        /** @var array{available: bool, products: list<array<string, mixed>>} */
        return $this->cache->remember($key, self::SEARCH_TTL, function () use ($query, $limit, $key): array {
            $raw = $this->get('/cgi/search.pl', [
                'search_terms' => $query,
                'search_simple' => 1,
                'action' => 'process',
                'json' => 1,
                'page_size' => 40,
                'sort_by' => 'unique_scans_n',
                'fields' => self::FIELDS,
            ]);

            if ($raw === null) {
                $this->cache->put($key, ['available' => false, 'products' => []], self::DOWN_TTL);

                return ['available' => false, 'products' => []];
            }

            $products = [];
            foreach ($raw['products'] ?? [] as $product) {
                $normalized = $this->normalize(is_array($product) ? $product : []);
                if ($normalized !== null) {
                    $products[] = $normalized;
                }
            }

            usort($products, fn (array $a, array $b) => [$b['sold_locally'], 0] <=> [$a['sold_locally'], 0]);

            return ['available' => true, 'products' => array_slice($products, 0, $limit)];
        });
    }

    /**
     * One product by barcode, or null when unknown.
     *
     * @return array{available: bool, product: array<string, mixed>|null}
     */
    public function product(string $barcode): array
    {
        $key = 'off:p:'.$barcode;

        /** @var array{available: bool, product: array<string, mixed>|null} */
        return $this->cache->remember($key, self::PRODUCT_TTL, function () use ($barcode, $key): array {
            $raw = $this->get('/api/v2/product/'.$barcode, ['fields' => self::FIELDS]);

            if ($raw === null) {
                $this->cache->put($key, ['available' => false, 'product' => null], self::DOWN_TTL);

                return ['available' => false, 'product' => null];
            }

            $product = (int) ($raw['status'] ?? 0) === 1 && is_array($raw['product'] ?? null) ? $this->normalize($raw['product']) : null;
            if ($product === null) {
                $this->cache->put($key, ['available' => true, 'product' => null], self::MISS_TTL);
            }

            return ['available' => true, 'product' => $product];
        });
    }

    /** Files a normalized product in the shared catalog (idempotent on barcode) and returns it. */
    public function store(array $product): Food
    {
        $existing = Food::query()->whereNull('user_id')->where('barcode', $product['barcode'])->first();
        $food = $existing ?? new Food;
        $generic = $product['generic_key'] !== null ? Food::query()->where('catalog_key', $product['generic_key'])->first() : null;

        $food->fill([
            'generic_id' => $generic?->id,
            'category' => $product['category'],
            'name' => $product['name'],
            'name_ar' => $product['name_ar'],
            'brand' => $product['brand'],
            'barcode' => $product['barcode'],
            'image_url' => $product['image_url'],
            'serving_grams' => $product['serving_grams'],
            'serving_label' => $product['serving_label'],
            'calories' => $product['calories'],
            'protein' => $product['protein'],
            'carbs' => $product['carbs'],
            'fat' => $product['fat'],
        ]);
        $food->source = FoodSource::OpenFoodFacts;
        $food->is_verified = false;
        $food->save();

        return $food;
    }

    /**
     * The subset we trust: a barcode, a name and complete per-100 g nutrition; else null.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>|null
     */
    public function normalize(array $p): ?array
    {
        $code = (string) ($p['code'] ?? '');
        $name = trim((string) ($p['product_name_en'] ?? $p['product_name'] ?? ''));
        $n = is_array($p['nutriments'] ?? null) ? $p['nutriments'] : [];

        $kcal = $n['energy-kcal_100g'] ?? (isset($n['energy_100g']) ? (float) $n['energy_100g'] / 4.184 : null);
        if (preg_match('/^\d{8,14}$/', $code) !== 1 || $name === '' || $kcal === null
            || ! isset($n['proteins_100g'], $n['carbohydrates_100g'], $n['fat_100g'])) {
            return null;
        }

        $servingGrams = 100.0;
        $servingLabel = '100 g';
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(g|ml)/i', (string) ($p['serving_size'] ?? ''), $m) === 1) {
            $servingGrams = (float) str_replace(',', '.', $m[1]);
            $servingLabel = (string) $p['serving_size'];
        }

        [$category, $genericKey] = $this->categorize(is_array($p['categories_tags'] ?? null) ? $p['categories_tags'] : []);
        $brand = trim((string) explode(',', (string) ($p['brands'] ?? ''))[0]);
        $countries = is_array($p['countries_tags'] ?? null) ? $p['countries_tags'] : [];
        $nameAr = trim((string) ($p['product_name_ar'] ?? ''));

        return [
            'barcode' => $code,
            'name' => mb_substr($name, 0, 120),
            'name_ar' => $nameAr !== '' ? mb_substr($nameAr, 0, 120) : null,
            'brand' => $brand !== '' ? mb_substr($brand, 0, 80) : null,
            'image_url' => $this->image($p),
            'category' => $category->value,
            'generic_key' => $genericKey,
            'serving_grams' => $servingGrams,
            'serving_label' => mb_substr($servingLabel, 0, 60),
            'calories' => round((float) $kcal, 1),
            'protein' => round((float) $n['proteins_100g'], 1),
            'carbs' => round((float) $n['carbohydrates_100g'], 1),
            'fat' => round((float) $n['fat_100g'], 1),
            'sold_locally' => in_array('en:saudi-arabia', $countries, true),
        ];
    }

    /**
     * @param  list<string>  $tags
     * @return array{0: FoodCategory, 1: string|null}
     */
    private function categorize(array $tags): array
    {
        foreach (self::CATEGORIES as $tag => $mapping) {
            if (in_array('en:'.$tag, $tags, true)) {
                return $mapping;
            }
        }

        return [FoodCategory::Other, null];
    }

    /** @param  array<string, mixed>  $p */
    private function image(array $p): ?string
    {
        foreach (['image_front_small_url', 'image_front_url'] as $field) {
            $url = $p[$field] ?? null;
            if (is_string($url) && str_starts_with($url, 'https://')) {
                return mb_substr($url, 0, 500);
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function get(string $path, array $query): ?array
    {
        try {
            $response = Http::withUserAgent('A-FIT/1.0 (github.com/AmrBinMohmmad/A-FIT-Backend)')
                ->timeout(8)
                ->get(self::BASE.$path, $query);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }
}
