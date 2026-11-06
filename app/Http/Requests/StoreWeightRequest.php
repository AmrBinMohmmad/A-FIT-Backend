<?php

declare(strict_types=1);

namespace App\Http\Requests;

class StoreWeightRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before:'.now()->addDays(2)->toDateString()],
            'weight_kg' => ['required', 'numeric', 'min:20', 'max:400'],
        ];
    }
}
