<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmailCode;
use App\Mail\VerifyLogin;

class AuthController extends Controller
{
    private function getMasterOtp(): ?string
    {
        return config('auth.master_otp') ? (string) config('auth.master_otp') : env('MASTER_OTP');
    }

    
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ], [
            'name.required'  => 'حقل الاسم مطلوب',
            'name.max'       => 'الاسم يجب ألا يتجاوز 255 حرفاً',
            'email.required' => 'حقل البريد الإلكتروني مطلوب',
            'email.email'    => 'يرجى إدخال بريد إلكتروني صالح',
            'email.unique'   => 'البريد الإلكتروني مسجل مسبقاً',
        ]);

        $code = (string) random_int(100000, 999999);

        Cache::put("register_pending_{$validated['email']}", [
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'code'  => $code,
        ], now()->addMinutes(10));

        Mail::to($validated['email'])->send(new VerifyEmailCode($code, $validated['name']));

        return response()->json([
            'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني',
            'email'   => $validated['email'],
        ], 200);
    }


    public function verifyRegister(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'code'  => ['required', 'string', 'size:6'],
        ], [
            'email.required' => 'حقل البريد الإلكتروني مطلوب',
            'email.email'    => 'يرجى إدخال بريد إلكتروني صالح',
            'code.required'  => 'رمز التحقق مطلوب',
            'code.size'      => 'رمز التحقق يجب أن يتكون من 6 أرقام',
        ]);

        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'هذا الحساب مسجل ومفعل مسبقاً، يمكنك تسجيل الدخول مباشرة',
            ], 400);
        }

        $cachedData = Cache::get("register_pending_{$validated['email']}");
        $masterOtp = $this->getMasterOtp();
        $isMasterOtp = ($masterOtp && $validated['code'] === $masterOtp);

        if (!$isMasterOtp && (!$cachedData || $cachedData['code'] !== $validated['code'])) {
            return response()->json([
                'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية',
            ], 422);
        }

        $name = $cachedData['name'] ?? ($request->name ?? 'مستخدم أي-فت');

        $user = User::create([
            'name'              => $name,
            'email'             => $validated['email'],
            'email_verified_at' => now(),
        ]);

        Cache::forget("register_pending_{$validated['email']}");

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'تم تأكيد البريد وإنشاء الحساب بنجاح',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }


    public function resendRegisterCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'حقل البريد الإلكتروني مطلوب',
            'email.email'    => 'يرجى إدخال بريد إلكتروني صالح',
        ]);

        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'هذا البريد مسجل بالفعل',
            ], 400);
        }

        $cachedData = Cache::get("register_pending_{$validated['email']}");
        if (!$cachedData) {
            return response()->json([
                'message' => 'انتهت صلاحية طلب التسجيل، يرجى إعادة إدخال بياناتك مجدداً',
            ], 404);
        }

        $code = (string) random_int(100000, 999999);
        $cachedData['code'] = $code;
        Cache::put("register_pending_{$validated['email']}", $cachedData, now()->addMinutes(10));

        Mail::to($validated['email'])->send(new VerifyEmailCode($code, $cachedData['name']));

        return response()->json([
            'message' => 'تم إرسال رمز تحقق جديد إلى بريدك الإلكتروني',
        ], 200);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
        ], [
            'email.required' => 'حقل البريد الإلكتروني مطلوب',
            'email.email'    => 'يرجى إدخال بريد إلكتروني صالح',
            'email.exists'   => 'البريد الإلكتروني غير مسجل لدينا',
        ]);

        $user = User::where('email', $validated['email'])->first();
        $code = (string) random_int(100000, 999999);

        Cache::put("login_verify_{$user->id}", $code, now()->addSeconds(90));

        Mail::to($user->email)->send(new VerifyLogin($code, $user->name));

        return response()->json([
            'message' => 'تم إرسال رمز الدخول إلى بريدك الإلكتروني',
        ], 200);
    }


    public function verifyLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
            'code'  => ['required', 'string', 'size:6'],
        ], [
            'email.required' => 'حقل البريد الإلكتروني مطلوب',
            'email.exists'   => 'البريد الإلكتروني غير مسجل لدينا',
            'code.required'  => 'رمز التحقق مطلوب',
            'code.size'      => 'رمز التحقق يجب أن يتكون من 6 أرقام',
        ]);

        $user = User::where('email', $validated['email'])->first();
        $cachedCode = Cache::get("login_verify_{$user->id}");
        $masterOtp = $this->getMasterOtp();
        $isMasterOtp = ($masterOtp && $validated['code'] === $masterOtp);

        if (!$isMasterOtp && (!$cachedCode || $cachedCode !== $validated['code'])) {
            return response()->json([
                'message' => 'رمز الدخول غير صحيح، حاول مرة أخرى',
            ], 400);
        }

        Cache::forget("login_verify_{$user->id}");

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'user'    => $user,
            'token'   => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح',
        ], 200);
    }
}
