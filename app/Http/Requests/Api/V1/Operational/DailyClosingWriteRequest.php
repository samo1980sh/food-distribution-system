<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\DailyClosing;
use Illuminate\Validation\Rule;

class DailyClosingWriteRequest extends OperationalWriteRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()?->can('create', DailyClosing::class) ?? false;
        }

        $closing = $this->route('dailyClosing');

        return $closing instanceof DailyClosing
            && ($this->user()?->can('update', $closing) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_reference' => $this->clientReferenceRules(),
            'closing_date' => $this->requiredOrSometimes(['date']),
            'vehicle_id' => ['sometimes', 'nullable', 'integer', Rule::exists('vehicles', 'id')->where('status', 'active')],
            'route_id' => ['sometimes', 'nullable', 'integer', Rule::exists('distribution_routes', 'id')->where('status', 'active')],
            'warehouse_id' => $this->requiredOrSometimes([
                'integer',
                Rule::exists('warehouses', 'id')->where('status', 'active'),
            ]),
            'sales_representative_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where(fn ($query) => $query->where('status', 'active')->where('type', 'sales_representative'))],
            'actual_cash_amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'items' => $this->isMethod('post')
                ? ['prohibited']
                : ['sometimes', 'array', 'max:500'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.actual_quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
