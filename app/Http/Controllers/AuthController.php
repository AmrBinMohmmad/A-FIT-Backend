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
    public function register(Request $request){
      $validated = $request->validate([
        'name' => ['required','string','unique:users','max:255'],
        'email' => ['email','string','required','unique:users,email','max:255'],
      ], [
        'name.required' => 'حقل الاسم مطلوب',
        'name.unique' => 'هذا الاسم مسجل مسبقاً',
        'name.max' => 'الاسم يجب ألا يتجاوز 255 حرفاً',
        'email.required' => 'حقل البريد الإلكتروني مطلوب',
        'email.email' => 'يرجى إدخال بريد إلكتروني صالح',
        'email.unique' => 'البريد الإلكتروني مسجل مسبقاً',
      ]);

      $user = User::create($validated);

      $code = (string) random_int(100000, 999999);     
     
      Cache::put("email_verify_{$user->id}",$code,now()->addMinutes(10));

      Mail::to($user->email)->send(new VerifyEmailCode($code,$user->name));

      $token = $user->createToken('api-token')->plainTextToken;
      return response()->json([
        'message' => 'تم إنشاء الحساب بنجاح',
        'user' => $user,
        'token' => $token,
      ],201);
    }

    public function verfiyEmail(Request $request){

      $validated = $request->validate([
        'code' => ['required','string','size:6'],
      ], [
        'code.required' => 'رمز التحقق مطلوب',
        'code.size' => 'رمز التحقق يجب أن يتكون من 6 أرقام',
      ]);

      $user = $request->user();

      if($user->hasVerifiedEmail()){
        return response()->json([
          'message' => 'البريد الإلكتروني مُفعّل مسبقاً',
        ],400);
      }
      $cachedCode = Cache::get("email_verify_{$user->id}");

      if(!$cachedCode || $cachedCode !== $request->code){
          return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية'], 422);
      }

      $user -> markEmailAsVerified();
      Cache::forget("email_verify_{$user->id}");

      return response()->json([
        'message' => 'تم تأكيد البريد الإلكتروني بنجاح',
        'user' => $user->fresh(),
    ]);
    }

    public function resendCode(Request $request){
      $user = $request->user();

      if($user->hasVerifiedEmail()){
         return response()->json([
          'message' => 'البريد الإلكتروني مُفعّل مسبقاً',
        ],400);
      }
      $code = (string) random_int(100000, 999999);     
      Cache::put("email_verify_{$user->id}",$code,now()->addMinutes(10));
      Mail::to($user->email)->send(new VerifyEmailCode($code,$user->name));
      return response()->json(['message' => 'تم إرسال رمز تحقق جديد إلى بريدك']);
    }
 
    
    public function login(Request $request){
         $validated = $request->validate([
            'email' => ['required', 'string','email','max:255','exists:users,email'],
        ], [
            'email.required' => 'حقل البريد الإلكتروني مطلوب',
            'email.email' => 'يرجى إدخال بريد إلكتروني صالح',
            'email.exists' => 'البريد الإلكتروني غير مسجل لدينا',
        ]);

       $code = (string) random_int(100000, 999999);     

       $user = User::where('email',$validated['email'])->first();

      Cache::put("login_verfiy_{$user->id}",$code , now()->addSeconds(90));

      Mail::to($user->email)->send(new VerifyLogin($code,$user->name));
      return response()->json(
        [
          'message' => 'تم إرسال رمز الدخول إلى بريدك'
        ],200
        );

       
    }
    public function verifyLogin(Request $request){
      $validated = $request->validate(
        [
          'code' => ['string','required','size:6'],
          'email' => ['required', 'string','email','max:255','exists:users,email'],

        ],
        [
          'code.required' => 'رمز التحقق مطلوب',
          'code.size' => 'رمز التحقق يجب أن يتكون من 6 أرقام',
          'email.required' => 'حقل البريد الإلكتروني مطلوب',
          'email.exists' => 'البريد الإلكتروني غير مسجل لدينا',
        ]
        );

        $user = User::where('email',$validated['email'])->first();

        $cachedCode = Cache::get("login_verfiy_{$user->id}");

        if(!$cachedCode || $cachedCode !== $validated['code']){
          return response()->json(
            [
              'message' => 'رمز الدخول غير صحيح، حاول مرة أخرى'
            ], 400
          );
        }

        Cache::forget("login_verfiy_{$user->id}");

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
            'message' => 'تم تسجيل الخروج بنجاح'
        ], 200);
    }
}

