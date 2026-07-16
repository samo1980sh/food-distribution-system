<?php

namespace App\Http\Requests\Api\V1\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobileSyncPullRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cursor' => ['sometimes', 'integer', 'min:0'],
            'limit' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.(int) config('mobile_api.sync_max_pull_limit', 500),
            ],
            'context_key' => [
                Rule::requiredIf(fn (): bool => (int) $this->input('cursor', 0) > 0),
                'nullable',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/',
            ],
        ];
    }

    public function cursor(): int
    {
        return (int) ($this->validated('cursor') ?? 0);
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit')
            ?? config('mobile_api.sync_default_pull_limit', 200));
    }

    public function contextKey(): ?string
    {
        $value = trim((string) ($this->validated('context_key') ?? ''));

        return $value === '' ? null : $value;
    }
}
