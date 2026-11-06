<?php

declare(strict_types=1);

namespace App\Http\Requests;

class WeightHistoryRequest extends DateRangeRequest
{
    protected int $maxDays = 366;
}
