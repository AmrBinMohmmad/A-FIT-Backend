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
      ]);

      $user = User::create($validated);

      $code = (string) random_int(100000, 999999);     
     
      Cache::put("email_verify_{$user->id}",$code,now()->addMinutes(10));

      Mail::to($user->email)->send(new VerifyEmailCode($code,$user->name));

      $token = $user->createToken('api-token')->plainTextToken;
      return response()->json([
        'message' => 'user created successfully',
        'user' => $user,
        'token' => $token,
      ],201);
    }

    public function verfiyEmail(Request $request){

      $validated = $request->validate([
        'code' => ['required','string','size:6'],
      ]);

      $user = $request->user();

      if($user->hasVerifiedEmail()){
        return response()->json([
          'message' => 'Email already verified',
        ],400);
      }
      $cachedCode = Cache::get("email_verify_{$user->id}");

      if(!$cachedCode || $cachedCode !== $request->code){
          return response()->json(['message' => 'Invalid or expired verification code.'], 422);
      }

      $user -> markEmailAsVerified();
      Cache::forget("email_verify_{$user->id}");

      return response()->json([
        'message' => 'Email verified successfully.',
        'user' => $user->fresh(),
    ]);
    }

    public function resendCode(Request $request){
      $user = $request->user();

      if($user->hasVerifiedEmail()){
         return response()->json([
          'message' => 'Email already verified',
        ],400);
      }
      $code = (string) random_int(100000, 999999);     
      Cache::put("email_verify_{$user->id}",$code,now()->addMinutes(10));
      Mail::to($user->email)->send(new VerifyEmailCode($code,$user->name));
      return response()->json(['message' => 'A new verification code has been sent.']);
    }
 
    
    public function login(Request $request){
         $validated = $request->validate([
            'email' => ['required', 'string','email','max:255','exists:users,email'],
        ]);

       $code = (string) random_int(100000, 999999);     

       $user = User::where('email',$validated['email'])->first();

      Cache::put("login_verfiy_{$user->id}",$code , now()->addSeconds(90));

      Mail::to($user->email)->send(new VerifyLogin($code,$user->name));
      return response()->json(
        [
          'message' => 'We sent you a code'
        ],200
        );

       
    }
    public function verifyLogin(Request $request){
      $validated = $request->validate(
        [
          'code' => ['string','required','size:6'],
          'email' => ['required', 'string','email','max:255','exists:users,email'],

        ]
        );

        $user = User::where('email',$validated['email'])->first();

        $cachedCode = Cache::get("login_verfiy_{$user->id}");

        if(!$cachedCode || $cachedCode !== $validated['code']){
          return response()->json(
            [
              'message' => 'Wrong code, try again'
            ], 400
          );
        }

        Cache::forget("login_verfiy_{$user->id}");

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;
        return response()->json([
        'message' => 'User logged in successfully',
        'user'    => $user,
        'token'   => $token,
    ], 200);

    }
}

