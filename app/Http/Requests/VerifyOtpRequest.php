<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\OtpCodes;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => OtpCodes::normalise((string) $this->input('email', '')),
            'code' => preg_replace('/\D/', '', (string) $this->input('code', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'code' => ['required', 'string', 'size:6'],
            'name' => ['nullable', 'string', 'min:2', 'max:60'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ];
    }
}
