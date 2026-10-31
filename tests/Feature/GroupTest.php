<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StepEntry;
use App\Models\User;
use App\Services\Groups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_join_and_list_groups(): void
    {
        $owner = $this->actingAsUser();

        $created = $this->postJson('/api/v1/groups', ['name' => 'رفاق المشي', 'owner_id' => 999]);
        $created->assertCreated()->assertJsonPath('data.my_role', 'owner')->assertJsonPath('data.members_count', 1);
        $code = (string) $created->json('data.invite_code');
        $this->assertSame(8, strlen($code));
        $this->assertDatabaseHas('groups', ['owner_id' => $owner->id, 'name' => 'رفاق المشي']);

        $friend = User::factory()->create();
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/groups/join', ['code' => strtolower(substr($code, 0, 4)).'-'.substr($code, 4)])
            ->assertOk()->assertJsonPath('data.my_role', 'member')->assertJsonPath('data.members_count', 2);

        $this->postJson('/api/v1/groups/join', ['code' => $code])->assertStatus(409);
        $this->postJson('/api/v1/groups/join', ['code' => 'ZZZZZZZZ'])->assertNotFound();

        $this->getJson('/api/v1/groups')->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_non_members_get_404_on_every_group_route(): void
    {
        $owner = User::factory()->create();
        $group = app(Groups::class)->create($owner, 'سري');
        $this->actingAsUser();

        $this->getJson("/api/v1/groups/{$group->id}")->assertNotFound();
        $this->getJson("/api/v1/groups/{$group->id}/leaderboard")->assertNotFound();
        $this->getJson("/api/v1/groups/{$group->id}/challenges")->assertNotFound();
        $this->patchJson("/api/v1/groups/{$group->id}", ['name' => 'xx'])->assertNotFound();
        $this->deleteJson("/api/v1/groups/{$group->id}")->assertNotFound();
        $this->postJson("/api/v1/groups/{$group->id}/invite/rotate")->assertNotFound();
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'سري']);
    }

    public function test_members_cannot_do_owner_things_but_owner_can(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $groups = app(Groups::class);
        $group = $groups->create($owner, 'الفريق');
        $groups->join($member, $group->invite_code);

        Sanctum::actingAs($member);
        $this->patchJson("/api/v1/groups/{$group->id}", ['name' => 'xx'])->assertForbidden();
        $this->deleteJson("/api/v1/groups/{$group->id}/members/{$owner->id}")->assertForbidden();
        $this->postJson("/api/v1/groups/{$group->id}/leave")->assertOk();
        $this->assertDatabaseMissing('group_members', ['group_id' => $group->id, 'user_id' => $member->id]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/groups/{$group->id}/leave")->assertStatus(409);
        $old = $group->invite_code;
        $rotated = $this->postJson("/api/v1/groups/{$group->id}/invite/rotate")->assertOk()->json('data.invite_code');
        $this->assertNotSame($old, $rotated);
        $this->deleteJson("/api/v1/groups/{$group->id}")->assertOk();
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_leaderboard_ranks_members_by_steps_in_the_period(): void
    {
        $owner = User::factory()->create(['name' => 'فيصل']);
        $sara = User::factory()->create(['name' => 'سارة']);
        $khalid = User::factory()->create(['name' => 'خالد']);
        $outsider = User::factory()->create();
        $groups = app(Groups::class);
        $group = $groups->create($owner, 'رفاق المشي');
        $groups->join($sara, $group->invite_code);
        $groups->join($khalid, $group->invite_code);

        StepEntry::factory()->create(['user_id' => $owner->id, 'date' => '2026-10-30', 'steps' => 7482]);
        StepEntry::factory()->create(['user_id' => $sara->id, 'date' => '2026-10-30', 'steps' => 9120]);
        StepEntry::factory()->create(['user_id' => $sara->id, 'date' => '2026-10-29', 'steps' => 4000]);
        StepEntry::factory()->create(['user_id' => $khalid->id, 'date' => '2026-10-30', 'steps' => 7482]);
        StepEntry::factory()->create(['user_id' => $outsider->id, 'date' => '2026-10-30', 'steps' => 99999]);

        Sanctum::actingAs($owner);
        $today = $this->getJson("/api/v1/groups/{$group->id}/leaderboard?period=today&date=2026-10-30");
        $today->assertOk()
            ->assertJsonPath('data.standings.0.name', 'سارة')
            ->assertJsonPath('data.standings.0.rank', 1)
            ->assertJsonPath('data.standings.1.rank', 2)
            ->assertJsonPath('data.standings.2.rank', 2)
            ->assertJsonCount(3, 'data.standings')
            ->assertJsonPath('data.refresh_after_seconds', 60);
        $this->assertTrue(collect($today->json('data.standings'))->firstWhere('name', 'فيصل')['is_me']);

        $week = $this->getJson("/api/v1/groups/{$group->id}/leaderboard?period=week&date=2026-10-30");
        $week->assertOk()->assertJsonPath('data.standings.0.steps', 13120);

        $show = $this->getJson("/api/v1/groups/{$group->id}");
        $show->assertOk()->assertJsonPath('data.group.id', $group->id)->assertJsonStructure(['data' => ['group', 'today']]);
    }

    public function test_dashboard_includes_group_summary(): void
    {
        $owner = $this->actingAsUser();
        $friend = User::factory()->create(['name' => 'سارة']);
        $groups = app(Groups::class);
        $group = $groups->create($owner, 'رفاق المشي');
        $groups->join($friend, $group->invite_code);
        StepEntry::factory()->create(['user_id' => $friend->id, 'date' => '2026-10-30', 'steps' => 9120]);
        StepEntry::factory()->create(['user_id' => $owner->id, 'date' => '2026-10-30', 'steps' => 7482]);

        $this->getJson('/api/v1/dashboard?date=2026-10-30')->assertOk()
            ->assertJsonPath('data.group.name', 'رفاق المشي')
            ->assertJsonPath('data.group.my_rank', 2)
            ->assertJsonPath('data.group.leader.name', 'سارة');
    }

    public function test_leaderboard_survives_the_cache_round_trip(): void
    {
        $owner = $this->actingAsUser();
        $group = app(Groups::class)->create($owner, 'cache');
        StepEntry::factory()->create(['user_id' => $owner->id, 'date' => '2026-10-30', 'steps' => 500]);

        // The file store serializes like redis does; the second call is served from it and must
        // still be a usable envelope (objects would come back as __PHP_Incomplete_Class).
        config(['cache.default' => 'file']);
        Cache::store('file')->flush();
        $this->getJson("/api/v1/groups/{$group->id}/leaderboard?period=today&date=2026-10-30")->assertOk();
        $this->getJson("/api/v1/groups/{$group->id}/leaderboard?period=today&date=2026-10-30")
            ->assertOk()->assertJsonPath('data.standings.0.steps', 500)->assertJsonPath('data.standings.0.rank', 1);
        $this->getJson('/api/v1/dashboard?date=2026-10-30')->assertOk()->assertJsonPath('data.group.my_rank', 1);
        Cache::store('file')->flush();
    }
}
