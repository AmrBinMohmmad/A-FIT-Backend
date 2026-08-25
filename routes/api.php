<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
Route::prefix('/auth')->group(function(){
    Route::post('/register', [AuthController::class ,'register']);
    Route::post('/login', [AuthController::class ,'login']);
    Route::post('/verifyLogin', [AuthController::class ,'verifyLogin']);
});

Route::prefix('/auth')->middleware('auth:sanctum')->group(function(){
    Route::post('/verifyCode',[AuthController::class,'verfiyEmail']);
    Route::post('/resendCode',[AuthController::class,'resendCode']);
});

