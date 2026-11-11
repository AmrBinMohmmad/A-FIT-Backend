<?php

declare(strict_types=1);

namespace App\Http\Requests;

/** Pull one online product (by barcode) into the shared catalog. */
class ImportFoodRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'regex:/^\d{8,14}$/'],
        ];
    }

    public function barcode(): string
    {
        return (string) $this->validated('barcode');
    }
}
