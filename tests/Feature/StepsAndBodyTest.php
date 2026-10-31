<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\StepEntry;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\WeightEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StepsAndBodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_step_sync_is_an_idempotent_batch_upsert(): void
    {
        $user = $this->actingAsUser();
        UserProfile::factory()->create(['user_id' => $user->id, 'daily_steps_goal' => 8000]);

        $payload = ['entries' => [
            ['date' => now()->toDateString(), 'steps' => 5120, 'source' => 'healthkit'],
            ['date' => now()->subDay()->toDateString(), 'steps' => 9001, 'source' => 'healthkit'],
        ]];

        $this->putJson('/api/v1/steps', $payload)->assertOk()->assertJsonCount(2, 'data.entries')->assertJsonPath('data.goal', 8000);
        $this->putJson('/api/v1/steps', ['entries' => [['date' => now()->toDateString(), 'steps' => 7482, 'source' => 'healthkit']]])->assertOk();

        $this->assertDatabaseCount('step_entries', 2);
        $this->assertDatabaseHas('step_entries', ['user_id' => $user->id, 'date' => now()->toDateString(), 'steps' => 7482]);
    }

    public function test_step_sync_rejects_oversized_batches_and_far_dates(): void
    {
        $this->actingAsUser();

        $tooMany = ['entries' => array_map(fn ($i) => ['date' => now()->subDays($i)->toDateString(), 'steps' => 1, 'source' => 'manual'], range(0, 31))];
        $this->putJson('/api/v1/steps', $tooMany)->assertStatus(422);

        $this->putJson('/api/v1/steps', ['entries' => [['date' => '2020-01-01', 'steps' => 1, 'source' => 'manual']]])->assertStatus(422);
        $this->putJson('/api/v1/steps', ['entries' => [['date' => now()->toDateString(), 'steps' => -1, 'source' => 'manual']]])->assertStatus(422);
    }

    public function test_step_history_is_bounded_and_private(): void
    {
        $user = $this->actingAsUser();
        StepEntry::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString(), 'steps' => 100]);
        StepEntry::factory()->create(['user_id' => User::factory()->create()->id, 'date' => now()->toDateString(), 'steps' => 999]);

        $from = now()->subDays(6)->toDateString();
        $to = now()->toDateString();
        $this->getJson("/api/v1/steps?from={$from}&to={$to}")->assertOk()->assertJsonCount(1, 'data.entries')->assertJsonPath('data.entries.0.steps', 100);

        $this->getJson('/api/v1/steps?from=2026-01-01&to=2026-06-30')->assertStatus(422);
    }

    public function test_water_is_an_absolute_daily_total(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/water/2026-10-30')->assertOk()->assertJsonPath('data.ml', 0);
        $this->putJson('/api/v1/water/2026-10-30', ['ml' => 500])->assertOk()->assertJsonPath('data.ml', 500);
        $this->putJson('/api/v1/water/2026-10-30', ['ml' => 1250])->assertOk()->assertJsonPath('data.ml', 1250);
        $this->assertDatabaseCount('water_entries', 1);
        $this->getJson('/api/v1/water/not-a-date')->assertNotFound();
    }

    public function test_weight_entries_are_per_day_and_private(): void
    {
        $user = $this->actingAsUser();

        $this->postJson('/api/v1/weights', ['date' => '2026-10-30', 'weight_kg' => 82.4])->assertOk()->assertJsonPath('data.weight_kg', 82.4);
        $this->postJson('/api/v1/weights', ['date' => '2026-10-30', 'weight_kg' => 82.1])->assertOk();
        $this->assertDatabaseCount('weight_entries', 1);

        $theirs = User::factory()->create()->weightEntries()->create(['date' => '2026-10-30', 'weight_kg' => 70]);
        $this->deleteJson("/api/v1/weights/{$theirs->id}")->assertNotFound();

        $mine = WeightEntry::query()->where('user_id', $user->id)->first();
        $this->deleteJson("/api/v1/weights/{$mine->id}")->assertOk();
        $this->getJson('/api/v1/weights?from=2026-10-01&to=2026-10-31')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_dashboard_bundles_the_day_in_one_call(): void
    {
        $user = $this->actingAsUser();
        UserProfile::factory()->create(['user_id' => $user->id]);
        StepEntry::factory()->create(['user_id' => $user->id, 'date' => '2026-10-30', 'steps' => 7482]);
        Meal::factory()->create(['user_id' => $user->id, 'eaten_on' => '2026-10-30', 'calories' => 420, 'protein' => 30]);
        Meal::factory()->create(['user_id' => $user->id, 'eaten_on' => '2026-10-30', 'calories' => 920, 'protein' => 50]);

        $res = $this->getJson('/api/v1/dashboard?date=2026-10-30');
        $res->assertOk()
            ->assertJsonPath('data.steps.steps', 7482)
            ->assertJsonPath('data.steps.goal', 10000)
            ->assertJsonPath('data.nutrition.calories', 1340)
            ->assertJsonPath('data.nutrition.protein', 80)
            ->assertJsonPath('data.nutrition.targets.calories', 2150)
            ->assertJsonPath('data.water.ml', 0)
            ->assertJsonPath('data.group', null)
            ->assertJsonPath('data.has_profile', true);
        $this->assertEnvelope($res, true);
    }
}
