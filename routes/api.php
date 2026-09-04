<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MealController;
use Illuminate\Support\Facades\Route;

// Public Authentication Routes
Route::prefix('/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verifyRegister', [AuthController::class, 'verifyRegister']);
    Route::post('/resendRegisterCode', [AuthController::class, 'resendRegisterCode']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verifyLogin', [AuthController::class, 'verifyLogin']);
});

// Authenticated Routes (Protected by Laravel Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('/meal')->group(function () {
        Route::get('/getUserMeals', [MealController::class, 'getUserMeals']);
        Route::get('/getAllMeals', [MealController::class, 'getAllMeals']);
        Route::get('/{id}', [MealController::class, 'getMeal']);
        Route::post('/createMeal', [MealController::class, 'createMeal']);
        Route::put('/editMeal/{id}', [MealController::class, 'editMeal']);
        Route::delete('/clearUserMeals', [MealController::class, 'clearUserMeals']);
        Route::delete('/deleteMeal/{id}', [MealController::class, 'deleteMeal']);
    });
});
