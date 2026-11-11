<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OpenFoodFacts;
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

    public const ENDPOINT = OpenFoodFacts::BASE.'/api/v2/search';

    public function handle(OpenFoodFacts $off): int
    {
        $only = $this->option('only');
        $categories = is_string($only) && $only !== '' ? array_intersect_key(OpenFoodFacts::CATEGORIES, [$only => true]) : OpenFoodFacts::CATEGORIES;
        $imported = 0;

        foreach (array_keys($categories) as $tag) {

            for ($page = 1; $page <= (int) $this->option('pages'); $page++) {
                $response = Http::withUserAgent('A-FIT/1.0 (github.com/AmrBinMohmmad/A-FIT-Backend)')
                    ->timeout(30)
                    ->get(self::ENDPOINT, [
                        'countries_tags_en' => $this->option('country'),
                        'categories_tags_en' => $tag,
                        'fields' => OpenFoodFacts::FIELDS,
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
                    $normalized = $off->normalize($product);
                    if ($normalized === null) {
                        continue;
                    }
                    // The search was already filtered by this tag; a product whose own
                    // categories are sparse still files under the aisle we asked for.
                    if ($normalized['generic_key'] === null) {
                        [$category, $genericKey] = OpenFoodFacts::CATEGORIES[$tag];
                        $normalized['category'] = $category->value;
                        $normalized['generic_key'] = $genericKey;
                    }
                    $off->store($normalized);
                    $imported++;
                }

                if (count($products) < 50) {
                    break;
                }
            }
        }

        $this->info("Imported or refreshed $imported products.");

        return self::SUCCESS;
    }
}
