<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MealController;
use Illuminate\Support\Facades\Route;


Route::prefix('/auth')->group(function () {
    
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verifyCode', [AuthController::class, 'verifyRegister']); // Alias
    Route::post('/resendRegisterCode', [AuthController::class, 'resendRegisterCode']);

    
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verifyLogin', [AuthController::class, 'verifyLogin']);
});


Route::prefix('/auth')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});


Route::prefix('/meal')->middleware('auth:sanctum')->group(function () {

    Route::get('/getUserMeals', [MealController::class, 'getUserMeals']);
    Route::get('/getAllMeals', [MealController::class, 'getAllMeals']);
    Route::get('/{id}', [MealController::class, 'getMeal']);

  
    Route::post('/createMeal', [MealController::class, 'createMeal']);

    
    Route::put('/updateMeal/{id}', [MealController::class, 'updateMeal']);
    Route::put('/{id}', [MealController::class, 'updateMeal']);

    
    Route::delete('/deleteMeal/{id}', [MealController::class, 'deleteMeal']);
    Route::delete('/{id}', [MealController::class, 'deleteMeal']);
});
