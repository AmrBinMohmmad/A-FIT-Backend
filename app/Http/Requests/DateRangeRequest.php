<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * Shared shape for bounded history reads: an inclusive [from, to] window of local
 * calendar days, capped so one request can never pull an unbounded table.
 */
abstract class DateRangeRequest extends AuthenticatedRequest
{
    protected int $maxDays = 90;

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $from = $this->input('from');
            $to = $this->input('to');
            if (is_string($from) && is_string($to) && CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= $this->maxDays) {
                $v->errors()->add('to', __('errors.steps_range'));
            }
        });
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('from'))->startOfDay();
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('to'))->startOfDay();
    }
}
