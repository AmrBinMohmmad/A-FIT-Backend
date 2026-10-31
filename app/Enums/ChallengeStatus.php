<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Ended = 'ended';
}
