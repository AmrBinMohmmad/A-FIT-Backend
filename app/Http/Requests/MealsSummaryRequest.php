<?php

declare(strict_types=1);

namespace App\Http\Requests;

class MealsSummaryRequest extends DateRangeRequest
{
    protected int $maxDays = 31;
}
