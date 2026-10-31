<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GroupRole;
use App\Models\Meal;
use App\Models\StepEntry;
use App\Models\User;
use App\Services\Groups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_user_with_profile_flag(): void
    {
        $user = $this->actingAsUser();

        $res = $this->getJson('/api/v1/me');
        $res->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonPath('data.has_profile', false);
        $this->assertEnvelope($res, true);
    }

    public function test_me_can_update_name_and_locale_but_not_email(): void
    {
        $user = $this->actingAsUser();

        $this->patchJson('/api/v1/me', ['name' => 'سارة', 'locale' => 'en', 'email' => 'x@y.z'])
            ->assertOk()->assertJsonPath('data.name', 'سارة')->assertJsonPath('data.locale', 'en');

        $this->assertSame($user->email, $user->fresh()->email);
        $this->patchJson('/api/v1/me', ['locale' => 'fr'])->assertStatus(422);
    }

    public function test_deleting_the_account_removes_data_and_hands_groups_over(): void
    {
        $owner = $this->actingAsUser();
        $friend = User::factory()->create();
        Meal::factory()->count(3)->create(['user_id' => $owner->id]);
        StepEntry::factory()->create(['user_id' => $owner->id]);

        $groups = app(Groups::class);
        $shared = $groups->create($owner, 'رفاق المشي');
        $groups->join($friend, $shared->invite_code);
        $solo = $groups->create($owner, 'وحدي');

        $this->deleteJson('/api/v1/me')->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseCount('meals', 0);
        $this->assertDatabaseCount('step_entries', 0);
        $this->assertDatabaseMissing('groups', ['id' => $solo->id]);
        $this->assertDatabaseHas('groups', ['id' => $shared->id, 'owner_id' => $friend->id]);
        $this->assertDatabaseHas('group_members', ['group_id' => $shared->id, 'user_id' => $friend->id, 'role' => GroupRole::Owner->value]);
    }

    public function test_profile_targets_are_computed_server_side(): void
    {
        $this->actingAsUser();

        $res = $this->putJson('/api/v1/me/profile', [
            'gender' => 'male', 'age' => 30, 'height' => 175, 'current_weight' => 80, 'target_weight' => 75,
            'activity_level' => 'moderate', 'daily_calories' => 99999, 'daily_steps_goal' => 12000,
        ]);

        $res->assertOk()->assertJsonPath('data.goal_type', 'lose')->assertJsonPath('data.daily_steps_goal', 12000);
        // Mifflin-St Jeor: 10*80 + 6.25*175 - 5*30 + 5 = 1748.75 → 1749; ×1.55 = 2711.
        // 5 kg to lose over ~12 weeks = 0.42 kg/week ≈ −460 kcal/day → 2251, rounded to 2250.
        $this->assertSame(2250, $res->json('data.daily_calories'));
        $this->assertNotSame(99999, $res->json('data.daily_calories'));
        $this->assertSame(2250, $this->getJson('/api/v1/me/profile')->json('data.daily_calories'));
    }

    public function test_unknown_routes_and_validation_errors_use_the_envelope(): void
    {
        $this->actingAsUser();

        $missing = $this->getJson('/api/v1/nope');
        $missing->assertNotFound();
        $this->assertEnvelope($missing, false);

        $invalid = $this->postJson('/api/v1/meals', ['name' => 'x']);
        $invalid->assertStatus(422);
        $this->assertEnvelope($invalid, false);
        $this->assertGreaterThan(10, mb_strlen((string) $invalid->json('message')));
    }

    public function test_messages_follow_accept_language(): void
    {
        $this->actingAsUser();

        $ar = $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/nope')->json('message');
        $en = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->getJson('/api/v1/nope')->json('message');

        $this->assertNotSame($ar, $en);
        $this->assertSame(__('errors.resource_not_found', [], 'en'), $en);
    }
}
