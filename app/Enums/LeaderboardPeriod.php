<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum LeaderboardPeriod: string
{
    case Today = 'today';
    case Week = 'week';
    case Month = 'month';

    /**
     * Inclusive [from, to] date bounds for the period that contains $anchor.
     * Weeks start on Sunday (the Gulf working week).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(CarbonImmutable $anchor): array
    {
        return match ($this) {
            self::Today => [$anchor, $anchor],
            self::Week => [$anchor->startOfWeek(CarbonImmutable::SUNDAY), $anchor->endOfWeek(CarbonImmutable::SATURDAY)],
            self::Month => [$anchor->startOfMonth(), $anchor->endOfMonth()],
        };
    }
}
