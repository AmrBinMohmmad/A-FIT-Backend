<?php

declare(strict_types=1);

namespace App\Http\Requests;

class LookupFoodsRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ];
    }
}
