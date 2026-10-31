<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StepSource;
use Illuminate\Validation\Rule;

/**
 * A batch of per-day totals. The client sends at most the last 31 days, only days whose
 * total changed since the previous sync, so a normal sync is one or two rows.
 */
class SyncStepsRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1', 'max:31'],
            'entries.*.date' => ['required', 'date_format:Y-m-d', 'after:'.now()->subDays(45)->toDateString(), 'before:'.now()->addDays(2)->toDateString()],
            'entries.*.steps' => ['required', 'integer', 'min:0', 'max:200000'],
            'entries.*.source' => ['required', Rule::enum(StepSource::class)],
        ];
    }
}
