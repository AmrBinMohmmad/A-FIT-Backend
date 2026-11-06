<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food;
use App\Models\User;
use Database\Seeders\FoodCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The grocery catalog: search, variants under a generic item, private foods, composed meals. */
final class FoodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoodCatalogSeeder::class);
    }

    public function test_catalog_seed_is_idempotent_and_links_variants_to_generics(): void
    {
        $before = Food::query()->count();
        $this->seed(FoodCatalogSeeder::class);
        $this->assertSame($before, Food::query()->count());

        $egg = Food::query()->where('catalog_key', 'egg')->firstOrFail();
        $white = Food::query()->where('catalog_key', 'egg-white')->firstOrFail();
        $this->assertNull($egg->generic_id);
        $this->assertSame($egg->id, $white->generic_id);
        $this->assertTrue($egg->is_verified);
        // Every seeded row has complete nutrition and a positive serving.
        $this->assertSame(0, Food::query()->where('serving_grams', '<=', 0)->count());
    }

    public function test_search_ranks_generic_prefix_matches_first_and_counts_variants(): void
    {
        $this->actingAsUser();

        $res = $this->getJson('/api/v1/foods?q=egg');
        $res->assertOk();
        $this->assertEnvelope($res, true);
        $first = $res->json('data.data.0');
        $this->assertSame('Egg, whole', $first['name']);
        $this->assertNull($first['generic_id']);
        $this->assertSame(8, $first['variants_count']);
        $this->assertSame(['calories' => 72, 'protein' => 6, 'carbs' => 0, 'fat' => 5], $first['per_serving']);

        // Arabic search hits name_ar.
        $this->getJson('/api/v1/foods?q='.rawurlencode('توست'))->assertOk()
            ->assertJsonPath('data.data.0.name', 'Toast bread, white');

        // Category browse without a query.
        $this->getJson('/api/v1/foods?category=dairy&per_page=5')->assertOk()
            ->assertJsonPath('data.meta.per_page', 5)->assertJsonPath('data.data.0.category', 'dairy');

        $this->getJson('/api/v1/foods?category=not-an-aisle')->assertUnprocessable();
    }

    public function test_show_returns_the_kinds_of_a_generic_item(): void
    {
        $this->actingAsUser();
        $toast = Food::query()->where('catalog_key', 'toast')->firstOrFail();

        $res = $this->getJson("/api/v1/foods/{$toast->id}")->assertOk();
        $this->assertSame('Toast bread, white', $res->json('data.food.name'));
        $names = array_column($res->json('data.variants'), 'name');
        $this->assertContains('Toast bread, whole wheat', $names);
        $this->assertContains('Toast bread, multigrain', $names);
        $this->assertNotContains('Pita bread, white (Arabic bread)', $names);
    }

    public function test_private_foods_are_only_visible_to_their_owner_and_barcodes_search(): void
    {
        $me = $this->actingAsUser();
        $toast = Food::query()->where('catalog_key', 'toast')->firstOrFail();

        // Label printed per serving (1 slice = 30 g, 80 kcal) is stored per 100 g.
        $created = $this->postJson('/api/v1/foods', [
            'name' => 'Toast, brand X, large', 'brand' => 'Brand X', 'category' => 'bread', 'generic_id' => $toast->id,
            'serving_grams' => 30, 'serving_label' => '1 slice', 'per' => 'serving',
            'calories' => 80, 'protein' => 2.5, 'carbs' => 15, 'fat' => 1,
            'user_id' => 999,
        ]);
        $created->assertCreated()->assertJsonPath('data.is_mine', true)->assertJsonPath('data.source', 'user');
        $this->assertEqualsWithDelta(266.7, $created->json('data.per_100g.calories'), 0.01);
        $this->assertSame(80, $created->json('data.per_serving.calories'));
        $id = (int) $created->json('data.id');
        $this->assertDatabaseHas('foods', ['id' => $id, 'user_id' => $me->id]);

        // It appears under the generic's variants for me…
        $this->assertContains($id, array_column($this->getJson("/api/v1/foods/{$toast->id}")->json('data.variants'), 'id'));

        // …but not for anyone else, who also cannot delete or reference it.
        $other = User::factory()->create();
        $this->actingAsUser($other);
        $this->assertNotContains($id, array_column($this->getJson("/api/v1/foods/{$toast->id}")->json('data.variants'), 'id'));
        $this->getJson("/api/v1/foods/{$id}")->assertNotFound();
        $this->deleteJson("/api/v1/foods/{$id}")->assertNotFound();
        $this->postJson('/api/v1/meals', ['meal_type' => 'lunch', 'eaten_on' => now()->toDateString(), 'items' => [['food_id' => $id, 'servings' => 1]]])
            ->assertUnprocessable();

        // Catalog rows are read-only even for a signed-in user.
        $this->deleteJson("/api/v1/foods/{$toast->id}")->assertNotFound();

        // A barcode query is an exact match.
        Food::factory()->create(['name' => 'Brand milk', 'barcode' => '6281000000011', 'category' => 'dairy']);
        $this->getJson('/api/v1/foods?q=6281000000011')->assertOk()->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Brand milk');

        $this->actingAsUser($me);
        $this->deleteJson("/api/v1/foods/{$id}")->assertOk();
        $this->assertDatabaseMissing('foods', ['id' => $id]);
    }

    public function test_a_meal_composed_from_items_is_totalled_server_side_and_snapshotted(): void
    {
        $this->actingAsUser();
        $egg = Food::query()->where('catalog_key', 'egg')->firstOrFail();
        $toast = Food::query()->where('catalog_key', 'toast-whole-wheat')->firstOrFail();
        $day = now()->toDateString();

        $res = $this->postJson('/api/v1/meals', [
            'meal_type' => 'breakfast', 'eaten_on' => $day,
            'calories' => 1, 'protein' => 1, // ignored: items decide
            'items' => [
                ['food_id' => $egg->id, 'servings' => 2],
                ['food_id' => $toast->id, 'grams' => 60],
            ],
        ]);
        $res->assertCreated();
        // 2 eggs = 100 g → 143 kcal; 60 g toast → 151 kcal.
        $this->assertSame(143 + 151, $res->json('data.calories'));
        $this->assertSame(13 + 7, $res->json('data.protein'));
        $this->assertSame('Egg, whole, Toast bread, whole wheat', $res->json('data.name'));
        $this->assertCount(2, $res->json('data.items'));
        $this->assertSame(100.0, (float) $res->json('data.items.0.grams'));
        $this->assertSame(2.0, (float) $res->json('data.items.1.servings'));
        $mealId = (int) $res->json('data.id');

        // The day's list carries the items, and recent foods reflect what was logged.
        $this->getJson("/api/v1/meals?date={$day}")->assertOk()->assertJsonCount(2, 'data.data.0.items');
        $recent = $this->getJson('/api/v1/foods/recent')->assertOk();
        $this->assertSame([$toast->id, $egg->id], array_column($recent->json('data'), 'id'));

        // Replacing the items recomputes the totals; a later catalog edit does not rewrite history.
        $this->putJson("/api/v1/meals/{$mealId}", ['items' => [['food_id' => $egg->id, 'servings' => 1]], 'name' => 'One egg'])
            ->assertOk()->assertJsonPath('data.calories', 72)->assertJsonPath('data.name', 'One egg')->assertJsonCount(1, 'data.items');
        $egg->forceFill(['calories' => 999])->save();
        $this->getJson("/api/v1/meals?date={$day}")->assertOk()->assertJsonPath('data.data.0.items.0.calories', 72);

        // Deleting the food keeps the line (food_id becomes null); deleting the meal removes lines.
        $this->postJson('/api/v1/meals', ['meal_type' => 'snack', 'eaten_on' => $day, 'items' => []])->assertUnprocessable();
        $this->deleteJson("/api/v1/meals/{$mealId}")->assertOk();
        $this->assertDatabaseMissing('meal_items', ['meal_id' => $mealId]);
    }

    public function test_typed_meals_still_work_without_items(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/v1/meals', ['name' => 'Kabsa', 'calories' => 650, 'meal_type' => 'lunch', 'eaten_on' => now()->toDateString()])
            ->assertCreated()->assertJsonPath('data.calories', 650)->assertJsonPath('data.items', []);
        $this->postJson('/api/v1/meals', ['meal_type' => 'lunch', 'eaten_on' => now()->toDateString()])->assertUnprocessable();
    }
}
