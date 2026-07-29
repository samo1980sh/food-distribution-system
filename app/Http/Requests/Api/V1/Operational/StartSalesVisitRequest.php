<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\SalesVisit;
use Illuminate\Foundation\Http\FormRequest;

class StartSalesVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('salesVisit');
        return $visit instanceof SalesVisit && ($this->user()?->can('start', $visit) ?? false);
    }

    public function rules(): array
    {
        return [
            'latitude' => ['sometimes', 'nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
