<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request){
      $validated = $request->validate([
        'name' => ['required','string','unique:users','max:255'],
        'email' => ['email','string','required','unique:users,email','max:255'],
      ]);
      $user = User::create($validated);
      $token = $user->createToken('api-token')->plainTextToken;
      return response()->json([
        'message' => 'user created successfully',
        'user' => $user,
        'token' => $token,
      ],201);
    }
 
    
    public function login(Request $request){
         $validated = $request->validate([
            'email' => ['required', 'string','email','max:255','exists:users,email'],
        ]);
        $user = User::where('email', $validated['email'])->first();
        if($user){
            $user->tokens()->delete();
            $token = $user->createToken('api-token')->plainTextToken;
            return response()->json([
                'message'=> 'user logged in successfully',
                'user'=>$user, 
                'token'=> $token
            ],201);
        }
        else{
            return response()->json([
                'message'=> 'user not found',
            ],404);
        }
    }
}
