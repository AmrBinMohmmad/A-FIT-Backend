<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Food;
use App\Models\User;

class FoodPolicy
{
    /** The shared catalog, or the user's own row. */
    public function view(User $user, Food $food): bool
    {
        return $food->user_id === null || $food->user_id === $user->id;
    }

    /** Only private foods can be changed, only by their owner; the catalog is read-only. */
    public function delete(User $user, Food $food): bool
    {
        return $food->user_id !== null && $food->user_id === $user->id;
    }
}
