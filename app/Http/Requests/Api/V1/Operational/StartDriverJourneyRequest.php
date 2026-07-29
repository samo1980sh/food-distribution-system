<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\DriverJourney;
use Illuminate\Foundation\Http\FormRequest;

class StartDriverJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $journey = $this->route('driverJourney');

        return $journey instanceof DriverJourney
            && ($this->user()?->can('start', $journey) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_odometer' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
