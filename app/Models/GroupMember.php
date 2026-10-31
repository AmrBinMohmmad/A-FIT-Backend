<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GroupRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GroupMember extends Pivot
{
    public $incrementing = true;

    protected $table = 'group_members';

    protected function casts(): array
    {
        return [
            'role' => GroupRole::class,
            'joined_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
