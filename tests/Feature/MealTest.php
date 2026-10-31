<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MealTest extends TestCase
{
    use RefreshDatabase;

    public function test_meals_are_listed_per_day_and_paginated(): void
    {
        $user = $this->actingAsUser();
        Meal::factory()->count(3)->create(['user_id' => $user->id, 'eaten_on' => '2026-10-30']);
        Meal::factory()->create(['user_id' => $user->id, 'eaten_on' => '2026-10-29']);
        Meal::factory()->create(); // someone else's

        $res = $this->getJson('/api/v1/meals?date=2026-10-30');
        $res->assertOk()->assertJsonCount(3, 'data.data')->assertJsonPath('data.meta.total', 3);
        $this->assertEnvelope($res, true);

        $this->getJson('/api/v1/meals?per_page=-5')->assertOk()->assertJsonPath('data.meta.total', 4);
    }

    public function test_meal_crud_and_summary(): void
    {
        $user = $this->actingAsUser();

        $created = $this->postJson('/api/v1/meals', [
            'name' => 'صدر دجاج', 'calories' => 420, 'protein' => 40, 'meal_type' => 'lunch', 'eaten_on' => '2026-10-30',
            'user_id' => 999,
        ]);
        $created->assertCreated()->assertJsonPath('data.calories', 420)->assertJsonPath('data.carbs', 0);
        $this->assertDatabaseHas('meals', ['user_id' => $user->id, 'name' => 'صدر دجاج']);

        $id = (int) $created->json('data.id');
        $this->putJson("/api/v1/meals/{$id}", ['calories' => 500])->assertOk()->assertJsonPath('data.calories', 500);

        $summary = $this->getJson('/api/v1/meals/summary?from=2026-10-29&to=2026-10-31');
        $summary->assertOk()->assertJsonPath('data.0.date', '2026-10-30')->assertJsonPath('data.0.calories', 500);

        $this->deleteJson("/api/v1/meals/{$id}")->assertOk();
        $this->assertDatabaseMissing('meals', ['id' => $id]);
    }

    public function test_another_users_meal_is_a_404_not_a_403(): void
    {
        $this->actingAsUser();
        $theirs = Meal::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->putJson("/api/v1/meals/{$theirs->id}", ['calories' => 1])->assertNotFound();
        $this->deleteJson("/api/v1/meals/{$theirs->id}")->assertNotFound();
        $this->assertDatabaseHas('meals', ['id' => $theirs->id, 'calories' => $theirs->calories]);
    }

    public function test_summary_window_is_bounded(): void
    {
        $this->actingAsUser();
        $this->getJson('/api/v1/meals/summary?from=2026-01-01&to=2026-12-31')->assertStatus(422);
    }
}
