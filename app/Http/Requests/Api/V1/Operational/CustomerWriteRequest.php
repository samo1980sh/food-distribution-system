<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\Customer;
use Illuminate\Validation\Rule;

class CustomerWriteRequest extends OperationalWriteRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            && ($this->user()?->can('create', Customer::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_reference' => $this->clientReferenceRules(),
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_type' => ['sometimes', Rule::in([
                'grocery', 'supermarket', 'restaurant', 'wholesaler',
                'mini_market', 'other',
            ])],
            'route_id' => [
                'required', 'integer',
                Rule::exists('distribution_routes', 'id')->where('status', 'active'),
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'credit_limit' => ['sometimes', 'numeric', 'min:0'],
            'credit_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'payment_type' => ['sometimes', Rule::in(['cash', 'credit', 'weekly', 'monthly'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'attach_to_today_journey' => ['sometimes', 'boolean'],
        ];
    }
}
