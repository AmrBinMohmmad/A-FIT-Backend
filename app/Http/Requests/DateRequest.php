<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;

/** A single optional local calendar day (`date=YYYY-MM-DD`), defaulting to today. */
class DateRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function day(): CarbonImmutable
    {
        $raw = $this->validated('date');

        return is_string($raw) ? CarbonImmutable::parse($raw)->startOfDay() : CarbonImmutable::today();
    }
}
