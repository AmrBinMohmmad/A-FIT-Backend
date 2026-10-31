<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Meal;
use App\Models\User;

class MealPolicy
{
    public function view(User $user, Meal $meal): bool
    {
        return $meal->user_id === $user->id;
    }

    public function update(User $user, Meal $meal): bool
    {
        return $meal->user_id === $user->id;
    }

    public function delete(User $user, Meal $meal): bool
    {
        return $meal->user_id === $user->id;
    }
}
