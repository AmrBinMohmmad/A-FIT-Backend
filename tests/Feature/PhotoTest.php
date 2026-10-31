<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Groups;
use App\Services\Photos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Avatars and group photos: typed by sniffed bytes, random names, owner-only for groups. */
final class PhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Photos::DISK);
    }

    public function test_user_uploads_replaces_and_removes_an_avatar(): void
    {
        $user = $this->actingAsUser();

        $first = $this->post('/api/v1/me/avatar', ['photo' => UploadedFile::fake()->image('me.png', 300, 300)]);
        $first->assertOk();
        $this->assertEnvelope($first, true);
        $url = (string) $first->json('data.avatar_url');
        $this->assertStringContainsString('/storage/avatars/', $url);
        $this->assertStringEndsWith('.png', $url);
        $this->assertStringNotContainsString('me.png', $url);

        $user->refresh();
        $firstPath = (string) $user->avatar_path;
        Storage::disk(Photos::DISK)->assertExists($firstPath);

        $this->post('/api/v1/me/avatar', ['photo' => UploadedFile::fake()->image('again.jpg', 200, 200)])->assertOk();
        Storage::disk(Photos::DISK)->assertMissing($firstPath);
        $secondPath = (string) $user->refresh()->avatar_path;
        Storage::disk(Photos::DISK)->assertExists($secondPath);

        $this->deleteJson('/api/v1/me/avatar')->assertOk()->assertJsonPath('data.avatar_url', null);
        Storage::disk(Photos::DISK)->assertMissing($secondPath);
        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_only_real_images_are_accepted(): void
    {
        $this->actingAsUser();

        // An SVG is script-bearing markup, a PDF is not an image, a 3 MB file is over the cap.
        $svg = UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->post('/api/v1/me/avatar', ['photo' => $svg])->assertUnprocessable();

        $pdf = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');
        $this->post('/api/v1/me/avatar', ['photo' => $pdf])->assertUnprocessable();

        $big = UploadedFile::fake()->image('big.jpg', 100, 100)->size(3000);
        $this->post('/api/v1/me/avatar', ['photo' => $big])->assertUnprocessable();

        $tiny = UploadedFile::fake()->image('tiny.jpg', 10, 10);
        $this->post('/api/v1/me/avatar', ['photo' => $tiny])->assertUnprocessable();

        $this->assertSame([], Storage::disk(Photos::DISK)->allFiles());
    }

    public function test_group_photo_is_owner_only_and_appears_in_standings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $groups = app(Groups::class);
        $group = $groups->create($owner, 'الفريق');
        $groups->join($member, $group->invite_code);

        Sanctum::actingAs($member);
        $this->post("/api/v1/groups/{$group->id}/photo", ['photo' => UploadedFile::fake()->image('g.jpg', 200, 200)])->assertForbidden();
        $this->post('/api/v1/me/avatar', ['photo' => UploadedFile::fake()->image('m.jpg', 200, 200)])->assertOk();

        Sanctum::actingAs($owner);
        $this->post("/api/v1/groups/{$group->id}/photo", ['photo' => UploadedFile::fake()->image('g.jpg', 200, 200)])
            ->assertOk()->assertJsonPath('data.my_role', 'owner');
        $this->assertStringContainsString('/storage/groups/', (string) $group->refresh()->photoUrl());

        $board = $this->getJson("/api/v1/groups/{$group->id}/leaderboard?period=today")->assertOk();
        $rows = collect($board->json('data.standings'))->keyBy('user_id');
        $this->assertNull($rows[$owner->id]['avatar_url']);
        $this->assertStringContainsString('/storage/avatars/', (string) $rows[$member->id]['avatar_url']);

        $this->getJson("/api/v1/groups/{$group->id}")->assertOk()
            ->assertJsonPath('data.group.photo_url', $group->photoUrl());

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);
        $this->post("/api/v1/groups/{$group->id}/photo", ['photo' => UploadedFile::fake()->image('x.jpg', 200, 200)])->assertNotFound();
    }

    public function test_deleting_the_account_or_group_removes_the_files(): void
    {
        $owner = $this->actingAsUser();
        $this->post('/api/v1/me/avatar', ['photo' => UploadedFile::fake()->image('me.jpg', 200, 200)])->assertOk();
        $group = app(Groups::class)->create($owner, 'وحيد');
        $this->post("/api/v1/groups/{$group->id}/photo", ['photo' => UploadedFile::fake()->image('g.jpg', 200, 200)])->assertOk();
        $this->assertCount(2, Storage::disk(Photos::DISK)->allFiles());

        $this->deleteJson('/api/v1/me')->assertOk();
        $this->assertSame([], Storage::disk(Photos::DISK)->allFiles());
    }
}
