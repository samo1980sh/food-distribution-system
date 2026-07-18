<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Enums\UserRole;
use App\Rules\ActiveEmployeeForOperationalRole;
use App\Models\VehicleExpense;
use Illuminate\Validation\Rule;

class VehicleExpenseWriteRequest extends OperationalWriteRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()?->can('create', VehicleExpense::class) ?? false;
        }

        $expense = $this->route('vehicleExpense');

        return $expense instanceof VehicleExpense
            && ($this->user()?->can('update', $expense) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_reference' => $this->clientReferenceRules(),
            'expense_date' => $this->requiredOrSometimes(['date']),
            'vehicle_id' => $this->requiredOrSometimes([
                'integer',
                Rule::exists('vehicles', 'id')->where('status', 'active'),
            ]),
            'warehouse_id' => $this->requiredOrSometimes([
                'integer',
                Rule::exists('warehouses', 'id')->where('status', 'active'),
            ]),
            'route_id' => ['sometimes', 'nullable', 'integer', Rule::exists('distribution_routes', 'id')->where('status', 'active')],
            'driver_id' => [
                'sometimes',
                'nullable',
                'integer',
                new ActiveEmployeeForOperationalRole(UserRole::DRIVER),
            ],
            'sales_representative_id' => [
                'sometimes',
                'nullable',
                'integer',
                new ActiveEmployeeForOperationalRole(UserRole::SALES_REPRESENTATIVE),
            ],
            'expense_type' => $this->requiredOrSometimes([Rule::in(['fuel', 'maintenance', 'washing', 'fees', 'parking', 'emergency', 'other'])]),
            'amount' => $this->requiredOrSometimes(['numeric', 'gt:0']),
            'payment_method' => $this->requiredOrSometimes([Rule::in(['cash', 'bank_transfer', 'cheque', 'other'])]),
            'receipt' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('mobile_api.expense_receipt_max_kb', 5120)],
            'remove_receipt' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
