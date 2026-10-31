<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateMeRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:60'],
            'locale' => ['sometimes', 'string', Rule::in((array) config('app.supported_locales'))],
        ];
    }
}
