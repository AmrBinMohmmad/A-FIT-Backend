<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponse;
use App\Models\Meal;

class UserProfileController extends Controller
{
    use ApiResponse;

    public function getProfile() {
    $profile = UserProfile::where('user_id', Auth::id())->first();
    
    return $this->successResponse($profile, 'تم جلب الملف بنجاح');
}

public function saveProfile(Request $request) {
    $validated = $request->validate([
        'gender' => ['required', 'in:male,female'],
        'age' => ['required', 'integer', 'min:10', 'max:120'],
        'height' => ['required', 'integer', 'min:50', 'max:260'],
        'current_weight' => ['required', 'integer', 'min:20', 'max:350'],
        'target_weight' => ['required', 'integer', 'min:20', 'max:350'],
        'activity_level' => ['required', 'in:sedentary,light,moderate,active,extra_active'],
        'goal_type' => ['nullable', 'in:lose,maintain,gain'],
        'daily_calories' => ['required', 'integer', 'min:800'],
        'daily_protein' => ['required', 'integer', 'min:0'],
        'daily_carbs' => ['required', 'integer', 'min:0'],
        'daily_fat' => ['required', 'integer', 'min:0'],
    ]);

    $profile = UserProfile::updateOrCreate(
        ['user_id' => Auth::id()],
        $validated
    );

    return $this->successResponse($profile, 'تم حفظ الخطة بنجاح');
}
}
