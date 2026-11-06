<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ChallengeType;
use Illuminate\Validation\Rule;

class StoreChallengeRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:80'],
            'type' => ['required', Rule::enum(ChallengeType::class)],
            'goal_steps' => ['required_if:type,'.ChallengeType::TargetSteps->value, 'nullable', 'integer', 'min:1000', 'max:10000000'],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->subDay()->toDateString()],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before:'.now()->addDays(366)->toDateString()],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_on.after_or_equal' => __('errors.challenge_window'),
            'starts_on.after_or_equal' => __('errors.challenge_window'),
        ];
    }
}
