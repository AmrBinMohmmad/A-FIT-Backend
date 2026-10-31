<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;
use Database\Factories\UserProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    /** @use HasFactory<UserProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'gender',
        'age',
        'height',
        'current_weight',
        'target_weight',
        'activity_level',
        'goal_type',
        'daily_calories',
        'daily_protein',
        'daily_carbs',
        'daily_fat',
        'daily_steps_goal',
        'water_goal_ml',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'activity_level' => ActivityLevel::class,
            'goal_type' => GoalType::class,
            'age' => 'integer',
            'height' => 'integer',
            'current_weight' => 'integer',
            'target_weight' => 'integer',
            'daily_calories' => 'integer',
            'daily_protein' => 'integer',
            'daily_carbs' => 'integer',
            'daily_fat' => 'integer',
            'daily_steps_goal' => 'integer',
            'water_goal_ml' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
