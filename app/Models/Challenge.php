<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChallengeStatus;
use App\Enums\ChallengeType;
use Carbon\CarbonImmutable;
use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'goal_steps',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChallengeType::class,
            'goal_steps' => 'integer',
            'starts_on' => 'immutable_date:Y-m-d',
            'ends_on' => 'immutable_date:Y-m-d',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** Status is derived from the window and the caller's local day, never stored. */
    public function statusOn(CarbonImmutable $today): ChallengeStatus
    {
        if ($today->lt($this->starts_on)) {
            return ChallengeStatus::Upcoming;
        }

        return $today->gt($this->ends_on) ? ChallengeStatus::Ended : ChallengeStatus::Active;
    }
}
