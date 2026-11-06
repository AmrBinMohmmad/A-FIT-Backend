<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FoodCategory;
use App\Enums\FoodSource;
use App\Models\Food;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Pulls brand products sold in a country from Open Food Facts (ODbL; the app credits it)
 * and files each one as a variant under the matching generic catalog item, so "eggs" lists
 * the brands on the shelf. Only products with complete per-100 g nutrition are taken.
 */
class ImportOpenFoodFacts extends Command
{
    protected $signature = 'foods:import-off {--country=saudi-arabia} {--pages=2} {--only= : one OFF category tag}';

    protected $description = 'Import brand products from Open Food Facts as variants of the generic catalog';

    public const ENDPOINT = 'https://world.openfoodfacts.org/api/v2/search';

    /** OFF category tag → [our category, generic catalog_key]. */
    public const CATEGORIES = [
        'chicken-eggs' => [FoodCategory::Eggs, 'egg'],
        'sandwich-breads' => [FoodCategory::Bread, 'toast'],
        'pita-breads' => [FoodCategory::Bread, 'pita'],
        'milks' => [FoodCategory::Dairy, 'milk-full'],
        'yogurts' => [FoodCategory::Dairy, 'yogurt-plain'],
        'labneh' => [FoodCategory::Dairy, 'labneh'],
        'cheeses' => [FoodCategory::Dairy, 'cheese-cheddar'],
        'dates' => [FoodCategory::Fruits, 'dates'],
        'hummus' => [FoodCategory::Legumes, 'hummus'],
        'canned-tunas' => [FoodCategory::Seafood, 'tuna-canned-water'],
        'breakfast-cereals' => [FoodCategory::Grains, 'cornflakes'],
        'fruit-juices' => [FoodCategory::Beverages, 'orange-juice'],
        'sodas' => [FoodCategory::Beverages, 'cola'],
        'chocolates' => [FoodCategory::Sweets, 'chocolate-milk'],
        'crisps' => [FoodCategory::Snacks, 'potato-chips'],
    ];

    public function handle(): int
    {
        $only = $this->option('only');
        $categories = is_string($only) && $only !== '' ? array_intersect_key(self::CATEGORIES, [$only => true]) : self::CATEGORIES;
        $imported = 0;

        foreach ($categories as $tag => [$category, $genericKey]) {
            $generic = Food::query()->where('catalog_key', $genericKey)->first();

            for ($page = 1; $page <= (int) $this->option('pages'); $page++) {
                $response = Http::withUserAgent('A-FIT/1.0 (github.com/AmrBinMohmmad/A-FIT-Backend)')
                    ->timeout(30)
                    ->get(self::ENDPOINT, [
                        'countries_tags_en' => $this->option('country'),
                        'categories_tags_en' => $tag,
                        'fields' => 'code,product_name,product_name_en,product_name_ar,brands,serving_size,nutriments',
                        'page_size' => 50,
                        'page' => $page,
                    ]);

                if (! $response->ok()) {
                    $this->warn("$tag page $page: HTTP {$response->status()}");
                    break;
                }

                /** @var list<array<string, mixed>> $products */
                $products = $response->json('products', []);
                foreach ($products as $product) {
                    if ($this->upsert($product, $category, $generic?->id)) {
                        $imported++;
                    }
                }

                if (count($products) < 50) {
                    break;
                }
            }
        }

        $this->info("Imported or refreshed $imported products.");

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $product */
    private function upsert(array $product, FoodCategory $category, ?int $genericId): bool
    {
        $code = (string) ($product['code'] ?? '');
        $name = trim((string) ($product['product_name_en'] ?? $product['product_name'] ?? ''));
        $n = is_array($product['nutriments'] ?? null) ? $product['nutriments'] : [];

        if ($code === '' || $name === '' || ! isset($n['energy-kcal_100g'], $n['proteins_100g'], $n['carbohydrates_100g'], $n['fat_100g'])) {
            return false;
        }

        $servingGrams = 100.0;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(g|ml)/i', (string) ($product['serving_size'] ?? ''), $m) === 1) {
            $servingGrams = (float) str_replace(',', '.', $m[1]);
        }

        $brand = trim((string) explode(',', (string) ($product['brands'] ?? ''))[0]) ?: null;

        $food = Food::query()->whereNull('user_id')->where('barcode', $code)->first() ?? new Food;
        $food->fill([
            'generic_id' => $genericId,
            'category' => $category,
            'name' => mb_substr($name, 0, 120),
            'name_ar' => isset($product['product_name_ar']) && $product['product_name_ar'] !== '' ? mb_substr((string) $product['product_name_ar'], 0, 120) : null,
            'brand' => $brand !== null ? mb_substr($brand, 0, 80) : null,
            'barcode' => $code,
            'serving_grams' => $servingGrams,
            'serving_label' => $servingGrams === 100.0 ? '100 g' : (string) ($product['serving_size'] ?? null),
            'calories' => round((float) $n['energy-kcal_100g'], 1),
            'protein' => round((float) $n['proteins_100g'], 1),
            'carbs' => round((float) $n['carbohydrates_100g'], 1),
            'fat' => round((float) $n['fat_100g'], 1),
        ]);
        $food->source = FoodSource::OpenFoodFacts;
        $food->is_verified = false;
        $food->save();

        return true;
    }
}
