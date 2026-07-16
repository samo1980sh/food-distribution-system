<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\VehicleExpense;
use Illuminate\Foundation\Http\FormRequest;

class VehicleExpenseRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('vehicleExpense');

        return $expense instanceof VehicleExpense
            && ($this->user()?->can('reject', $expense) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
