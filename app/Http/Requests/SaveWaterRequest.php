<?php

declare(strict_types=1);

namespace App\Http\Requests;

class SaveWaterRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'ml' => ['required', 'integer', 'min:0', 'max:15000'],
        ];
    }
}
