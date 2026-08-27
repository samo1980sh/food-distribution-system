<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\SalesJourney;
use Illuminate\Foundation\Http\FormRequest;

class StartSalesJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $journey = $this->route('salesJourney');
        return $journey instanceof SalesJourney && ($this->user()?->can('start', $journey) ?? false);
    }

    public function rules(): array
    {
        return [
            'start_odometer' => ['required', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
