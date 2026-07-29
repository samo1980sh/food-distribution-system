<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\SalesVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteSalesVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('salesVisit');
        return $visit instanceof SalesVisit && ($this->user()?->can('complete', $visit) ?? false);
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in([
                'invoice_created', 'collection_recorded', 'return_recorded',
                'mixed', 'no_order', 'customer_closed', 'other',
            ])],
            'latitude' => ['sometimes', 'nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'notes' => ['required_if:outcome,other', 'nullable', 'string', 'max:5000'],
        ];
    }
}
