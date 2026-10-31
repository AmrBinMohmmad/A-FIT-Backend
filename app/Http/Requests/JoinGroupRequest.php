<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Group;

class JoinGroupRequest extends AuthenticatedRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Group::normaliseCode((string) $this->input('code', ''))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:8'],
        ];
    }
}
