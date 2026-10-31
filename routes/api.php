<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StepController;
use App\Http\Controllers\WaterController;
use App\Http\Controllers\WeightController;
use Illuminate\Support\Facades\Route;

// All routes are served under /api/v1 (bootstrap/app.php apiPrefix — Rakaya D10.1).

Route::prefix('auth/otp')->group(function (): void {
    Route::post('request', [AuthController::class, 'requestCode'])->middleware('throttle:otp-request');
    Route::post('verify', [AuthController::class, 'verify'])->middleware('throttle:otp-verify');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);

    Route::get('me', [MeController::class, 'show']);
    Route::get('me/profile', [ProfileController::class, 'show']);
    Route::get('dashboard', [DashboardController::class, 'show']);

    Route::get('meals', [MealController::class, 'index']);
    Route::get('meals/summary', [MealController::class, 'summary']);
    Route::get('meals/frequent', [MealController::class, 'frequent']);
    Route::get('steps', [StepController::class, 'history']);
    Route::get('water/{date}', [WaterController::class, 'show']);
    Route::get('weights', [WeightController::class, 'history']);

    Route::get('groups', [GroupController::class, 'index']);
    Route::get('groups/{group}', [GroupController::class, 'show']);
    Route::get('groups/{group}/leaderboard', [GroupController::class, 'leaderboard']);
    Route::get('groups/{group}/challenges', [ChallengeController::class, 'index']);
    Route::get('challenges/{challenge}', [ChallengeController::class, 'show']);

    // Step sync has its own tighter limiter: a client should batch, not stream.
    Route::put('steps', [StepController::class, 'sync'])->middleware('throttle:steps-sync');

    Route::middleware('throttle:mutations')->group(function (): void {
        Route::patch('me', [MeController::class, 'update']);
        Route::delete('me', [MeController::class, 'destroy']);
        Route::put('me/profile', [ProfileController::class, 'update']);

        Route::post('meals', [MealController::class, 'store']);
        Route::put('meals/{meal}', [MealController::class, 'update']);
        Route::delete('meals/{meal}', [MealController::class, 'destroy']);

        Route::put('water/{date}', [WaterController::class, 'update']);
        Route::post('weights', [WeightController::class, 'store']);
        Route::delete('weights/{weightEntry}', [WeightController::class, 'destroy']);

        Route::post('groups', [GroupController::class, 'store']);
        Route::post('groups/join', [GroupController::class, 'join']);
        Route::patch('groups/{group}', [GroupController::class, 'update']);
        Route::delete('groups/{group}', [GroupController::class, 'destroy']);
        Route::post('groups/{group}/leave', [GroupController::class, 'leave']);
        Route::post('groups/{group}/invite/rotate', [GroupController::class, 'rotateCode']);
        Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember']);

        Route::post('groups/{group}/challenges', [ChallengeController::class, 'store']);
        Route::delete('challenges/{challenge}', [ChallengeController::class, 'destroy']);
    });
});
