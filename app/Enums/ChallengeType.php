<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeType: string
{
    /** Whoever walks the most steps inside the window wins. */
    case MostSteps = 'most_steps';

    /** First member to reach goal_steps inside the window wins; ties share. */
    case TargetSteps = 'target_steps';
}
