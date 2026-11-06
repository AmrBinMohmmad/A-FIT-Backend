<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WeightEntry;

class WeightEntryPolicy
{
    public function delete(User $user, WeightEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }
}
