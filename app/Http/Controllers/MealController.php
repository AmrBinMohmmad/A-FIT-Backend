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

        return $this->successResponse($meal,'تم إنشاء الوجبة بنجاح');
    }
    public function editMeal(Request $request, int $id){

        $meal = Meal::where('id',$id)->where('user_id',Auth::id())->first();

        if(!$meal){
            return $this-> errorResponse('الوجبة غير موجودة او غير مصرح لك بتعديلها');
          };

        $validated = $request->validate(
            [
            'name' => ['sometimes','nullable', 'string', 'min:4'],
            'calories' => ['sometimes','nullable', 'integer', 'min:0'],
            'protein' => ['sometimes','nullable', 'integer', 'min:0'],
            'carbs' => ['sometimes','nullable', 'integer', 'min:0'],
            'fat' => ['sometimes','nullable', 'integer', 'min:0'],
            'meal_type' => ['sometimes','nullable', 'string', 'in:breakfast,lunch,dinner,snack,other'],
            ], [
            'name.required' => 'حقل اسم الوجبة مطلوب',
            'name.min' => 'اسم الوجبة يجب أن يتكون من 4 أحرف على الأقل',
            'calories.integer' => 'السعرات يجب أن تكون رقماً صحيحاً',
            'protein.integer' => 'البروتين يجب أن يكون رقماً صحيحاً',
            'carbs.integer' => 'الكاربوهيدرات يجب أن تكون رقماً صحيحاً',
            'fat.integer' => 'الدهون يجب أن تكون رقماً صحيحاً',
            'meal_type.in' => 'نوع الوجبة غير صالح',
        ]
            );
        $meal->update($validated);

        return $this->successResponse($meal->fresh(),'تم تحديث الوجبة بنجاح');
    }
    public function deleteMeal(int $id){
        $meal = Meal::where('id',$id)->where('user_id',Auth::id())->first();
        if(!$meal){
            return $this-> errorResponse('الوجبة غير موجودة او غير مصرح لك بحذفها');
        };

        $meal->delete();

        return $this->successResponse(null,'تم حذف الوجبة بنجاح');
    }

    public function destroyMeal(int $id){
        return $this->deleteMeal($id);
    }
}
