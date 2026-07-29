<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\DriverDelivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitDriverDeliveryOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $delivery = $this->route('driverDelivery');

        return $delivery instanceof DriverDelivery
            && ($this->user()?->can('submitOutcome', $delivery) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['delivered', 'partial', 'failed'])],
            'recipient_name' => ['required_unless:outcome,failed', 'nullable', 'string', 'max:255'],
            'recipient_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'proof_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'failure_reason' => ['required_if:outcome,failed', 'nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => ['required', 'integer', 'distinct'],
            'items.*.delivered_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.returned_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
