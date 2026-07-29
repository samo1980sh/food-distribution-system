<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\DriverJourney;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenDriverJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('openToday', DriverJourney::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'route_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('distribution_routes', 'id')->where('status', 'active'),
            ],
        ];
    }
}
