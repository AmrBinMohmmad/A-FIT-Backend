<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OtpResult;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\SignedInRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Mail\OtpCodeMail;
use App\Models\User;
use App\Services\OtpCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Passwordless sign-in: one "continue with e-mail" flow.
 *
 *   POST auth/otp/request {email}            → always the same 200, whether or not the
 *                                              address has an account (no enumeration).
 *   POST auth/otp/verify  {email, code, name?} → existing user: token. New e-mail without a
 *                                              name: 200 + requires_name so the client asks
 *                                              for it. New e-mail with a name: account + token.
 */
class AuthController extends Controller
{
    public function requestCode(RequestOtpRequest $request, OtpCodes $codes): JsonResponse
    {
        /** @var string $email */
        $email = $request->validated('email');

        $code = $codes->issue($email);

        if ($code === null) {
            return $this->error(__('errors.otp_cooldown'), 429);
        }

        Mail::to($email)->locale(app()->getLocale())->queue(new OtpCodeMail($code));

        return $this->success(null, __('messages.otp_sent'));
    }

    public function verify(VerifyOtpRequest $request, OtpCodes $codes): JsonResponse
    {
        /** @var string $email */
        $email = $request->validated('email');
        /** @var string $code */
        $code = $request->validated('code');
        $name = $request->validated('name');

        $result = $codes->verify($email, $code);

        if ($result === OtpResult::Locked) {
            return $this->error(__('errors.otp_locked'), 429);
        }

        if ($result === OtpResult::Invalid) {
            return $this->error(__('errors.otp_invalid'), 400);
        }

        $user = User::query()->where('email', $email)->first();
        $isNew = false;

        if ($user === null) {
            if (! is_string($name) || trim($name) === '') {
                // The code stays valid so the follow-up call (with the name) can finish.
                return $this->success([
                    'requires_name' => true,
                    'token' => null,
                    'user' => null,
                    'is_new' => true,
                ], __('messages.otp_name_needed'));
            }

            $user = User::query()->create([
                'name' => trim($name),
                'email' => $email,
                'locale' => app()->getLocale(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $isNew = true;
        }

        $codes->consume($email);

        $deviceName = (string) ($request->validated('device_name') ?? 'mobile');
        $token = $user->createToken(mb_substr($deviceName, 0, 80))->plainTextToken;

        $user->load('profile');

        return $this->success([
            'requires_name' => false,
            'token' => $token,
            'user' => new UserResource($user),
            'is_new' => $isNew,
        ], $isNew ? __('messages.account_created') : __('messages.signed_in'));
    }

    public function logout(SignedInRequest $request): JsonResponse
    {
        $token = $request->actor()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return $this->success(null, __('messages.signed_out'));
    }
}
