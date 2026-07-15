<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_id' => ['required', 'string', 'min:8', 'max:100'],
            'device_name' => ['required', 'string', 'max:100'],
            'platform' => [
                'required',
                'string',
                Rule::in(['android', 'ios']),
            ],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'device_id' => trim((string) $this->input('device_id')),
            'device_name' => trim((string) $this->input('device_name')),
            'platform' => mb_strtolower(trim((string) $this->input('platform'))),
            'app_version' => $this->filled('app_version')
                ? trim((string) $this->input('app_version'))
                : null,
        ]);
    }
}
