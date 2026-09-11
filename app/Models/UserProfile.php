<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
