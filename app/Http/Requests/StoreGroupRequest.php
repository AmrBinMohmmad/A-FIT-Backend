<?php

declare(strict_types=1);

namespace App\Http\Requests;

class StoreGroupRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ];
    }
}
