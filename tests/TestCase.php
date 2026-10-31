<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Signs a fresh (or given) user in with a Sanctum token for the next requests. */
    protected function actingAsUser(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /** Every API response, success or failure, is exactly {flag, data, message}. */
    protected function assertEnvelope(TestResponse $response, bool $flag): void
    {
        $response->assertJsonStructure(['flag', 'data', 'message'])
            ->assertJsonPath('flag', $flag)
            ->assertJsonMissingPath('errors');
        $this->assertSame(['flag', 'data', 'message'], array_keys($response->json()));
        $this->assertIsString($response->json('message'));
    }
}
