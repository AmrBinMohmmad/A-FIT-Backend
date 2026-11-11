<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FoodSource;
use App\Models\Food;
use Illuminate\Database\Seeder;

/**
 * Loads database/data/foods.json — idempotent on `catalog_key`, so it can run on every deploy
 * and a corrected value in the JSON updates the row without touching user data.
 */
class FoodCatalogSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{foods: list<array<string, mixed>>} $data */
        $data = json_decode((string) file_get_contents(database_path('data/foods.json')), true, 512, JSON_THROW_ON_ERROR);

        $ids = [];
        // Generic items first so a variant can resolve its parent in the same pass.
        $rows = collect($data['foods'])->sortBy(fn (array $row) => isset($row['generic']) ? 1 : 0);

        foreach ($rows as $row) {
            $food = Food::query()->updateOrCreate(
                ['catalog_key' => $row['key']],
                [
                    'user_id' => null,
                    'generic_id' => isset($row['generic']) ? ($ids[$row['generic']] ?? null) : null,
                    'category' => $row['category'],
                    'source' => FoodSource::Catalog,
                    'name' => $row['name'],
                    'name_ar' => $row['name_ar'] ?? null,
                    'brand' => null,
                    'serving_grams' => $row['serving_grams'],
                    'serving_label' => $row['serving_label'] ?? null,
                    'serving_label_ar' => $row['serving_label_ar'] ?? null,
                    'calories' => $row['calories'],
                    'protein' => $row['protein'],
                    'carbs' => $row['carbs'],
                    'fat' => $row['fat'],
                    'is_verified' => true,
                ],
            );
            $ids[$row['key']] = $food->id;
        }
    }
}
