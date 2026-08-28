<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\MealException;


class MealController extends Controller
{
    use ApiResponse;
    public function getUserMeals(){
        $userMeals = Meal::where('user_id', Auth::id())->get();
        if (!$userMeals){
           return $this->errorResponse('تعذر جلب وجباتك');
        }
        return $this->successResponse($userMeals,'تم جلب وجباتك بنجاح');
    }

    public function getAllMeals(){
        $meals = Meal::with('user')->get();
         if (!$meals){
           return $this->errorResponse('تعذر جلب وجباتك');
        }
        return $this->successResponse($meals,'تم جلب وجباتك بنجاح');
    }

    public function getMeal(int $id){
        $meal = Meal::where('id',$id)
        ->first();
        if(!$meal){
            return $this->errorResponse('الوجبة غير موجودة');
        }
        return $this->successResponse($meal,'تم جلب الوجبة بنجاح');
    }

    public function createMeal(Request $request){
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:4'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'protein' => ['nullable', 'integer', 'min:0'],
            'carbs' => ['nullable', 'integer', 'min:0'],
            'fat' => ['nullable', 'integer', 'min:0'],
            'meal_type' => ['nullable', 'string', 'in:breakfast,lunch,dinner,snack,other'],
        ], [
            'name.required' => 'حقل اسم الوجبة مطلوب',
            'name.min' => 'اسم الوجبة يجب أن يتكون من 4 أحرف على الأقل',
            'calories.integer' => 'السعرات يجب أن تكون رقماً صحيحاً',
            'protein.integer' => 'البروتين يجب أن يكون رقماً صحيحاً',
            'carbs.integer' => 'الكاربوهيدرات يجب أن تكون رقماً صحيحاً',
            'fat.integer' => 'الدهون يجب أن تكون رقماً صحيحاً',
            'meal_type.in' => 'نوع الوجبة غير صالح',
        ]);

        $validated['user_id'] = Auth::id();
        $meal = Meal::create($validated);

        if(!$meal){
            return $this->errorResponse();
        }

        return response()->json([
            'message' => 'تم إنشاء الوجبة بنجاح',
            'meal' => $meal,
        ], 201);
    }
}
