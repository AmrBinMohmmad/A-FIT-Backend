<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\OtpCodes;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    /** Public endpoint: throttled by the route, open to every caller by design. */
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => OtpCodes::normalise((string) $this->input('email', ''))]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
        ];
    }
}
