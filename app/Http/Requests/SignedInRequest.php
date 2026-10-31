<?php

declare(strict_types=1);

namespace App\Http\Requests;

/** A body-less authenticated request (show / delete / logout). */
class SignedInRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}
