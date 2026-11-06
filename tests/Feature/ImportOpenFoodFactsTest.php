<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ImportOpenFoodFacts;
use App\Models\Food;
use Database\Seeders\FoodCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ImportOpenFoodFactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_with_complete_nutrition_become_variants_of_the_generic(): void
    {
        $this->seed(FoodCatalogSeeder::class);
        Http::fake([
            ImportOpenFoodFacts::ENDPOINT.'*' => Http::response(['products' => [
                ['code' => '6281001100012', 'product_name' => 'Omega-3 eggs', 'product_name_ar' => 'بيض أوميغا', 'brands' => 'Farm A, Group B',
                    'serving_size' => '50 g', 'nutriments' => ['energy-kcal_100g' => 155, 'proteins_100g' => 12.5, 'carbohydrates_100g' => 1.1, 'fat_100g' => 11]],
                ['code' => '6281001100029', 'product_name' => 'Mystery eggs', 'nutriments' => ['energy-kcal_100g' => 150]], // incomplete → skipped
            ]]),
        ]);

        $this->artisan('foods:import-off', ['--only' => 'chicken-eggs', '--pages' => 1])->assertSuccessful();
        $this->artisan('foods:import-off', ['--only' => 'chicken-eggs', '--pages' => 1])->assertSuccessful(); // idempotent

        $egg = Food::query()->where('catalog_key', 'egg')->firstOrFail();
        $imported = Food::query()->where('barcode', '6281001100012')->get();
        $this->assertCount(1, $imported);
        $this->assertSame($egg->id, $imported[0]->generic_id);
        $this->assertSame('Farm A', $imported[0]->brand);
        $this->assertSame('open_food_facts', $imported[0]->source->value);
        $this->assertSame(50.0, $imported[0]->serving_grams);
        $this->assertFalse($imported[0]->is_verified);
        $this->assertDatabaseMissing('foods', ['barcode' => '6281001100029']);
    }
}
