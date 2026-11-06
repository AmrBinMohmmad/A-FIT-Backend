<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OtpResult;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and verifies e-mail one-time codes.
 *
 * Security properties: the code is never stored in clear (bcrypt hash in the cache),
 * a development master code is honoured only in local/testing (see devMasterCode()),
 * it lives TTL_MINUTES, a per-e-mail resend cooldown stops mail floods, and after
 * MAX_ATTEMPTS wrong guesses the code is destroyed so brute force cannot continue
 * against the same code (6 digits = 1e6 space; 5 guesses ≈ 0.0005%).
 * The e-mail is hashed in the cache key so the store never lists addresses.
 */
final class OtpCodes
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const COOLDOWN_SECONDS = 60;

    /** Environments in which the development master code may ever be honoured. */
    private const MASTER_CODE_ENVIRONMENTS = ['local', 'testing'];

    public function __construct(private readonly Cache $cache) {}

    /**
     * The development master code, or null when it must not be honoured. The environment
     * check is hard-coded on purpose: a production .env carrying DEV_MASTER_OTP is a
     * misconfiguration, not a backdoor.
     */
    public static function devMasterCode(): ?string
    {
        if (! app()->environment(self::MASTER_CODE_ENVIRONMENTS)) {
            return null;
        }

        $code = config('auth.dev_master_otp');

        return is_string($code) && preg_match('/^\d{6}$/', $code) === 1 ? $code : null;
    }

    /** Returns the plaintext code to e-mail, or null while the resend cooldown is active. */
    public function issue(string $email): ?string
    {
        $email = self::normalise($email);

        if ($this->cache->has($this->cooldownKey($email))) {
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->cache->put($this->codeKey($email), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        $this->cache->put($this->cooldownKey($email), 1, now()->addSeconds(self::COOLDOWN_SECONDS));

        return $code;
    }

    public function verify(string $email, string $code): OtpResult
    {
        $email = self::normalise($email);
        $key = $this->codeKey($email);

        $master = self::devMasterCode();
        if ($master !== null && hash_equals($master, $code)) {
            return OtpResult::Valid;
        }

        /** @var array{hash: string, attempts: int}|null $record */
        $record = $this->cache->get($key);

        if ($record === null) {
            return OtpResult::Invalid;
        }

        if (Hash::check($code, $record['hash'])) {
            return OtpResult::Valid;
        }

        $record['attempts']++;

        if ($record['attempts'] >= self::MAX_ATTEMPTS) {
            $this->cache->forget($key);

            return OtpResult::Locked;
        }

        $this->cache->put($key, $record, now()->addMinutes(self::TTL_MINUTES));

        return OtpResult::Invalid;
    }

    /** Burns the code once it has produced a session. */
    public function consume(string $email): void
    {
        $this->cache->forget($this->codeKey(self::normalise($email)));
    }

    public static function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function codeKey(string $email): string
    {
        return 'otp:code:'.hash('sha256', $email);
    }

    private function cooldownKey(string $email): string
    {
        return 'otp:cooldown:'.hash('sha256', $email);
    }
}
