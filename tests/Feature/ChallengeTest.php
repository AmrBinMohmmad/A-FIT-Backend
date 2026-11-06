<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StepEntry;
use App\Models\User;
use App\Services\Groups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_create_and_read_challenges_with_standings(): void
    {
        $owner = User::factory()->create(['name' => 'فيصل']);
        $sara = User::factory()->create(['name' => 'سارة']);
        $groups = app(Groups::class);
        $group = $groups->create($owner, 'رفاق المشي');
        $groups->join($sara, $group->invite_code);

        Sanctum::actingAs($sara);
        $start = now()->toDateString();
        $end = now()->addDays(6)->toDateString();
        $created = $this->postJson("/api/v1/groups/{$group->id}/challenges", [
            'title' => 'تحدي ١٠٠ ألف خطوة', 'type' => 'target_steps', 'goal_steps' => 100000, 'starts_on' => $start, 'ends_on' => $end,
        ]);
        $created->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.days_left', 7);
        $id = (int) $created->json('data.id');

        StepEntry::factory()->create(['user_id' => $sara->id, 'date' => $start, 'steps' => 9120]);
        StepEntry::factory()->create(['user_id' => $owner->id, 'date' => $start, 'steps' => 7482]);

        $show = $this->getJson("/api/v1/challenges/{$id}");
        $show->assertOk()
            ->assertJsonPath('data.standings.0.name', 'سارة')
            ->assertJsonPath('data.standings.0.steps', 9120)
            ->assertJsonPath('data.winner_user_id', null);

        $this->getJson("/api/v1/groups/{$group->id}/challenges")->assertOk()->assertJsonPath('data.meta.total', 1)->assertJsonMissingPath('data.data.0.standings');

        // The group owner may delete a member's challenge; an outsider sees nothing.
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/challenges/{$id}")->assertNotFound();
        $this->deleteJson("/api/v1/challenges/{$id}")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/challenges/{$id}")->assertOk();
        $this->assertDatabaseMissing('challenges', ['id' => $id]);
    }

    public function test_challenge_window_and_type_are_validated(): void
    {
        $owner = $this->actingAsUser();
        $group = app(Groups::class)->create($owner, 'x');

        $this->postJson("/api/v1/groups/{$group->id}/challenges", [
            'title' => 'bad', 'type' => 'most_steps', 'starts_on' => now()->addDays(3)->toDateString(), 'ends_on' => now()->toDateString(),
        ])->assertStatus(422);

        $this->postJson("/api/v1/groups/{$group->id}/challenges", [
            'title' => 'no goal', 'type' => 'target_steps', 'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
        ])->assertStatus(422);

        $this->postJson("/api/v1/groups/{$group->id}/challenges", [
            'title' => 'bogus', 'type' => 'longest_nap', 'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
        ])->assertStatus(422);
    }
}
