<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Http\Requests\SaveProfileRequest;
use App\Http\Requests\SignedInRequest;
use App\Http\Resources\UserProfileResource;
use App\Services\NutritionPlan;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(SignedInRequest $request): JsonResponse
    {
        $profile = $request->actor()->profile;

        return $this->success($profile ? new UserProfileResource($profile) : null);
    }

    /** Physical inputs in, server-computed daily targets out (Tier 1.1). */
    public function update(SaveProfileRequest $request): JsonResponse
    {
        $data = $request->validated();

        $plan = NutritionPlan::compute(
            Gender::from($data['gender']),
            (int) $data['age'],
            (int) $data['height'],
            (int) $data['current_weight'],
            (int) $data['target_weight'],
            ActivityLevel::from($data['activity_level']),
        );

        $profile = $request->actor()->profile()->updateOrCreate(
            [],
            [
                'gender' => $data['gender'],
                'age' => $data['age'],
                'height' => $data['height'],
                'current_weight' => $data['current_weight'],
                'target_weight' => $data['target_weight'],
                'activity_level' => $data['activity_level'],
                'goal_type' => $plan['goal_type'],
                'daily_calories' => $plan['daily_calories'],
                'daily_protein' => $plan['daily_protein'],
                'daily_carbs' => $plan['daily_carbs'],
                'daily_fat' => $plan['daily_fat'],
                ...(isset($data['daily_steps_goal']) ? ['daily_steps_goal' => $data['daily_steps_goal']] : []),
                ...(isset($data['water_goal_ml']) ? ['water_goal_ml' => $data['water_goal_ml']] : []),
            ],
        );

        return $this->success(new UserProfileResource($profile), __('messages.profile_saved'));
    }
}
