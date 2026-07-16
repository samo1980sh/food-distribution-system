<?php

namespace App\Http\Requests\Api\V1\Operational;

use Illuminate\Foundation\Http\FormRequest;

class OperationalIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'route_id' => ['nullable', 'integer', 'min:1'],
            'vehicle_id' => ['nullable', 'integer', 'min:1'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }

    public function searchTerm(): ?string
    {
        $value = trim((string) ($this->validated('search') ?? ''));

        return $value === '' ? null : $value;
    }

    public function sortDirection(): string
    {
        return (string) ($this->validated('sort_direction') ?? 'desc');
    }
}
