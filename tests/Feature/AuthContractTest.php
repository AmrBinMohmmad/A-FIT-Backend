<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Mail\OtpCodeMail;
use App\Models\User;
use App\Services\OtpCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Follows kit/enforcement/backend/tests/Feature/AuthContractTest.php (Rakaya D2), adapted
 * to the passwordless OTP flow: the plaintext PAT lives at exactly data.token.
 */
final class AuthContractTest extends TestCase
{
    use RefreshDatabase;

    private function issueCode(string $email): string
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/otp/request', ['email' => $email])->assertOk();
        Mail::assertQueued(OtpCodeMail::class, 1);

        /** @var OtpCodeMail $mail */
        $mail = Mail::queued(OtpCodeMail::class)->first();

        return $mail->code;
    }

    public function test_request_answers_identically_for_known_and_unknown_emails(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/v1/auth/otp/request', ['email' => 'known@example.com']);
        $unknown = $this->postJson('/api/v1/auth/otp/request', ['email' => 'nobody@example.com']);

        $this->assertEnvelope($known, true);
        $this->assertEnvelope($unknown, true);
        $this->assertSame($known->json(), $unknown->json());
        Mail::assertQueued(OtpCodeMail::class, 2);
    }

    public function test_verify_returns_plaintext_token_at_data_token_for_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'faisal@example.com']);
        $code = $this->issueCode('faisal@example.com');

        $res = $this->postJson('/api/v1/auth/otp/verify', ['email' => 'FAISAL@example.com', 'code' => $code]);

        $res->assertOk()
            ->assertJsonStructure(['flag', 'data' => ['token', 'user', 'requires_name', 'is_new'], 'message'])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.requires_name', false)
            ->assertJsonPath('data.is_new', false)
            ->assertJsonPath('data.user.id', $user->id);
        $this->assertIsString($res->json('data.token'));
        $this->assertNotEmpty($res->json('data.token'));

        // The code is single-use once it produced a session.
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'faisal@example.com', 'code' => $code])->assertStatus(400);
    }

    public function test_new_email_needs_a_name_then_creates_the_account(): void
    {
        $code = $this->issueCode('new@example.com');

        $first = $this->postJson('/api/v1/auth/otp/verify', ['email' => 'new@example.com', 'code' => $code]);
        $first->assertOk()->assertJsonPath('data.requires_name', true)->assertJsonPath('data.token', null);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);

        $second = $this->postJson('/api/v1/auth/otp/verify', ['email' => 'new@example.com', 'code' => $code, 'name' => 'نورة']);
        $second->assertOk()->assertJsonPath('data.is_new', true)->assertJsonPath('data.user.name', 'نورة');
        $this->assertIsString($second->json('data.token'));
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_wrong_code_is_the_envelope_and_locks_after_five_attempts(): void
    {
        $code = $this->issueCode('lock@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 4; $i++) {
            $res = $this->postJson('/api/v1/auth/otp/verify', ['email' => 'lock@example.com', 'code' => $wrong]);
            $res->assertStatus(400);
            $this->assertEnvelope($res, false);
        }

        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'lock@example.com', 'code' => $wrong])->assertStatus(429);

        // The real code is now dead too: brute force cannot continue against it.
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'lock@example.com', 'code' => $code])->assertStatus(400);
    }

    public function test_codes_are_never_stored_in_clear(): void
    {
        $code = $this->issueCode('hash@example.com');
        $record = Cache::get('otp:code:'.hash('sha256', 'hash@example.com'));

        $this->assertIsArray($record);
        $this->assertNotSame($code, $record['hash']);
        $this->assertStringNotContainsString($code, $record['hash']);
    }

    public function test_resend_within_cooldown_is_refused(): void
    {
        $this->issueCode('cool@example.com');
        $res = $this->postJson('/api/v1/auth/otp/request', ['email' => 'cool@example.com']);
        $res->assertStatus(429);
        $this->assertEnvelope($res, false);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        User::factory()->create(['email' => 'out@example.com']);
        $code = $this->issueCode('out@example.com');
        $token = (string) $this->postJson('/api/v1/auth/otp/verify', ['email' => 'out@example.com', 'code' => $code])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        // The test app keeps the resolved guard between requests; a real client gets a fresh one.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_all_revokes_every_token_of_the_account(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone')->plainTextToken;
        $keep = $user->createToken('tablet')->plainTextToken;
        $this->assertSame(2, $user->tokens()->count());

        $this->withToken($keep)->postJson('/api/v1/auth/logout-all')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_sign_in_keeps_at_most_ten_live_sessions(): void
    {
        $user = User::factory()->create(['email' => 'many@example.com']);
        for ($i = 0; $i < 12; $i++) {
            $user->createToken("device-$i");
        }
        $oldest = (int) $user->tokens()->oldest('id')->value('id');

        $code = app(OtpCodes::class)->issue('many@example.com');
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'many@example.com', 'code' => $code])->assertOk();

        $this->assertSame(AuthController::MAX_SESSIONS, $user->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldest]);
    }

    public function test_protected_route_requires_a_valid_token(): void
    {
        $res = $this->getJson('/api/v1/me');
        $res->assertUnauthorized();
        $this->assertEnvelope($res, false);
    }

    public function test_token_expiration_is_configured(): void
    {
        $this->assertNotNull(config('sanctum.expiration'));
        $this->assertGreaterThan(0, (int) config('sanctum.expiration'));
    }

    public function test_dev_master_code_signs_in_any_email_in_testing(): void
    {
        config(['auth.dev_master_otp' => '000000']);
        User::factory()->create(['email' => 'master@example.com']);

        $res = $this->postJson('/api/v1/auth/otp/verify', ['email' => 'master@example.com', 'code' => '000000']);
        $res->assertOk()->assertJsonPath('data.requires_name', false);
        $this->assertIsString($res->json('data.token'));

        // A new e-mail works too, through the normal name step.
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'fresh@example.com', 'code' => '000000'])
            ->assertOk()->assertJsonPath('data.requires_name', true);
    }

    public function test_dev_master_code_is_ignored_outside_local_and_testing(): void
    {
        config(['auth.dev_master_otp' => '000000']);
        User::factory()->create(['email' => 'prod@example.com']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertNull(OtpCodes::devMasterCode());
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'prod@example.com', 'code' => '000000'])->assertStatus(400);

        $this->app->detectEnvironment(fn () => 'testing');
    }

    public function test_no_master_code_when_the_knob_is_unset(): void
    {
        config(['auth.dev_master_otp' => null]);
        User::factory()->create(['email' => 'none@example.com']);

        $this->assertNull(OtpCodes::devMasterCode());
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'none@example.com', 'code' => '000000'])->assertStatus(400);
    }
}
