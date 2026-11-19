<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food;
use App\Services\OpenFoodFacts;
use Database\Seeders\FoodCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Live product lookup through Open Food Facts: normalized, cached, imported once, degrades when down. */
final class FoodLookupTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function product(string $code, string $name, array $extra = []): array
    {
        return $extra + [
            'code' => $code, 'product_name' => $name, 'brands' => 'Almarai, Group', 'serving_size' => '200 ml',
            'image_front_small_url' => "https://images.openfoodfacts.org/$code.jpg",
            'categories_tags' => ['en:dairies', 'en:milks'], 'countries_tags' => ['en:saudi-arabia'],
            'nutriments' => ['energy-kcal_100g' => 61, 'proteins_100g' => 3.2, 'carbohydrates_100g' => 4.8, 'fat_100g' => 3.3],
        ];
    }

    public function test_name_lookup_normalizes_ranks_local_products_and_is_cached(): void
    {
        $this->seed(FoodCatalogSeeder::class);
        Cache::flush();
        $this->actingAsUser();
        Http::fake([
            OpenFoodFacts::BASE.'/cgi/search.pl*' => Http::response(['products' => [
                $this->product('4000000000001', 'Foreign milk', ['countries_tags' => ['en:germany']]),
                $this->product('6281000000011', 'Full fat milk'),
                ['code' => '6281000000029', 'product_name' => 'No nutrition'],
            ]]),
        ]);

        $res = $this->getJson('/api/v1/foods/lookup?q=milk')->assertOk();
        $this->assertEnvelope($res, true);
        $this->assertTrue($res->json('data.available'));
        $this->assertCount(2, $res->json('data.results'));
        $this->assertSame('6281000000011', $res->json('data.results.0.barcode'));
        $this->assertSame('Almarai', $res->json('data.results.0.brand'));
        $this->assertSame('dairy', $res->json('data.results.0.category'));
        $this->assertSame(200.0, (float) $res->json('data.results.0.serving_grams'));
        $this->assertSame(122, $res->json('data.results.0.per_serving.calories'));
        $this->assertStringStartsWith('https://images.openfoodfacts.org/', $res->json('data.results.0.image_url'));
        $this->assertNull($res->json('data.results.0.food_id'));

        $this->getJson('/api/v1/foods/lookup?q=milk')->assertOk();
        Http::assertSentCount(1); // the second call is served from the cache

        $this->getJson('/api/v1/foods/lookup?q=m')->assertUnprocessable();
    }

    public function test_barcode_lookup_import_is_idempotent_and_files_under_the_generic(): void
    {
        $this->seed(FoodCatalogSeeder::class);
        Cache::flush();
        $this->actingAsUser();
        Http::fake([
            OpenFoodFacts::BASE.'/api/v2/product/6281000000011*' => Http::response(['status' => 1, 'product' => $this->product('6281000000011', 'Full fat milk', ['product_name_ar' => 'حليب كامل الدسم'])]),
            OpenFoodFacts::BASE.'/api/v2/product/6281000000099*' => Http::response(['status' => 0]),
        ]);

        $this->getJson('/api/v1/foods/lookup/6281000000011')->assertOk()
            ->assertJsonPath('data.result.name', 'Full fat milk')->assertJsonPath('data.result.food_id', null);
        $this->getJson('/api/v1/foods/lookup/6281000000099')->assertOk()->assertJsonPath('data.result', null);
        $this->getJson('/api/v1/foods/lookup/not-a-code')->assertNotFound();

        $first = $this->postJson('/api/v1/foods/import', ['barcode' => '6281000000011'])->assertCreated();
        $id = (int) $first->json('data.id');
        $this->assertSame('حليب كامل الدسم', $first->json('data.name_ar'));
        $this->assertSame('open_food_facts', $first->json('data.source'));
        $milk = Food::query()->where('catalog_key', 'milk-full')->firstOrFail();
        $this->assertSame($milk->id, Food::query()->findOrFail($id)->generic_id);

        // Second import returns the same shared row; the lookup now reports it as in the catalog.
        $this->postJson('/api/v1/foods/import', ['barcode' => '6281000000011'])->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(1, Food::query()->where('barcode', '6281000000011')->count());
        $this->getJson('/api/v1/foods/lookup/6281000000011')->assertOk()->assertJsonPath('data.result.food_id', $id);
        $this->postJson('/api/v1/foods/import', ['barcode' => '6281000000099'])->assertNotFound();

        // The scanned barcode now resolves through the normal search too.
        $this->getJson('/api/v1/foods?q=6281000000011')->assertOk()->assertJsonPath('data.data.0.id', $id)
            ->assertJsonPath('data.data.0.image_url', 'https://images.openfoodfacts.org/6281000000011.jpg');
    }

    public function test_outage_degrades_to_unavailable_without_an_error(): void
    {
        Cache::flush();
        $this->actingAsUser();
        Http::fake([OpenFoodFacts::BASE.'/*' => Http::response('down', 503)]);

        $this->getJson('/api/v1/foods/lookup?q=milk')->assertOk()->assertJsonPath('data.available', false)->assertJsonCount(0, 'data.results');
        $this->getJson('/api/v1/foods/lookup/6281000000011')->assertOk()->assertJsonPath('data.available', false);
        $this->postJson('/api/v1/foods/import', ['barcode' => '6281000000011'])->assertStatus(503);
    }
}
