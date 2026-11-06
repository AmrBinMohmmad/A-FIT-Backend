<?php

declare(strict_types=1);

namespace App\Http\Requests;

class StepsHistoryRequest extends DateRangeRequest
{
    protected int $maxDays = 90;
}
