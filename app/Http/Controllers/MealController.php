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
    public function getUserMeals(Request $request){
        $request->validate([
            'date'=> ['nullable','date_format:Y-m-d'],
        ]);

        $query = Meal::where('user_id',Auth::id());

        if($request->filled('date')){
            $query->whereDate('created_at',$request->date);
        };

        $userMeals = $query->latest()->get();

        return $this->successResponse($userMeals,'تم جلب وجباتك بنجاح');
    }

    public function getAllMeals(){
        $meals = Meal::with('user')->latest()->get();
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
            'name' => ['required', 'string', 'min:2'],
            'calories' => ['required', 'numeric', 'min:0'],
            'protein' => ['nullable', 'numeric', 'min:0'],
            'carbs' => ['nullable', 'numeric', 'min:0'],
            'fat' => ['nullable', 'numeric', 'min:0'],
            'meal_type' => ['nullable', 'string', 'in:breakfast,lunch,dinner,snack,other'],
        ], [
            'name.required' => 'حقل اسم الوجبة مطلوب',
            'name.min' => 'اسم الوجبة يجب أن يتكون من حرفين على الأقل',
            'calories.required' => 'حقل السعرات الحرارية مطلوب',
            'calories.numeric' => 'السعرات يجب أن تكون قيمة رقمية',
            'protein.numeric' => 'البروتين يجب أن يكون قيمة رقمية',
            'carbs.numeric' => 'الكاربوهيدرات يجب أن تكون قيمة رقمية',
            'fat.numeric' => 'الدهون يجب أن تكون قيمة رقمية',
            'meal_type.in' => 'نوع الوجبة غير صالح',
        ]);

        $validated['calories'] = (int) round($validated['calories']);
        if (isset($validated['protein'])) $validated['protein'] = (int) round($validated['protein']);
        if (isset($validated['carbs'])) $validated['carbs'] = (int) round($validated['carbs']);
        if (isset($validated['fat'])) $validated['fat'] = (int) round($validated['fat']);

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
            'name' => ['sometimes','nullable', 'string', 'min:2'],
            'calories' => ['sometimes','nullable', 'numeric', 'min:0'],
            'protein' => ['sometimes','nullable', 'numeric', 'min:0'],
            'carbs' => ['sometimes','nullable', 'numeric', 'min:0'],
            'fat' => ['sometimes','nullable', 'numeric', 'min:0'],
            'meal_type' => ['sometimes','nullable', 'string', 'in:breakfast,lunch,dinner,snack,other'],
            ], [
            'name.required' => 'حقل اسم الوجبة مطلوب',
            'name.min' => 'اسم الوجبة يجب أن يتكون من حرفين على الأقل',
            'calories.numeric' => 'السعرات يجب أن تكون قيمة رقمية',
            'protein.numeric' => 'البروتين يجب أن يكون قيمة رقمية',
            'carbs.numeric' => 'الكاربوهيدرات يجب أن تكون قيمة رقمية',
            'fat.numeric' => 'الدهون يجب أن تكون قيمة رقمية',
            'meal_type.in' => 'نوع الوجبة غير صالح',
        ]
            );

        if (isset($validated['calories'])) $validated['calories'] = (int) round($validated['calories']);
        if (isset($validated['protein'])) $validated['protein'] = (int) round($validated['protein']);
        if (isset($validated['carbs'])) $validated['carbs'] = (int) round($validated['carbs']);
        if (isset($validated['fat'])) $validated['fat'] = (int) round($validated['fat']);

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

    public function clearUserMeals(){
        Meal::where('user_id', Auth::id())->delete();
        return $this->successResponse(null, 'تم مسح جميع وجباتك بنجاح');
    }
}
