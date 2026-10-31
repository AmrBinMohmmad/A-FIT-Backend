<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LeaderboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

class LeaderboardRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::enum(LeaderboardPeriod::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function period(): LeaderboardPeriod
    {
        $raw = $this->validated('period');

        return is_string($raw) ? LeaderboardPeriod::from($raw) : LeaderboardPeriod::Today;
    }

    public function day(): CarbonImmutable
    {
        $raw = $this->validated('date');

        return is_string($raw) ? CarbonImmutable::parse($raw)->startOfDay() : CarbonImmutable::today();
    }
}
