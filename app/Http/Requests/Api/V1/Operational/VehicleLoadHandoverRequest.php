<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\VehicleLoad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class VehicleLoadHandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('vehicleLoad');

        return $record instanceof VehicleLoad
            && Gate::allows('acknowledge', $record);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'handover_status' => ['required', Rule::in(['received', 'discrepancy'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.received_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
