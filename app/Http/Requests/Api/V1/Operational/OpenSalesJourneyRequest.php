<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\SalesJourney;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenSalesJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('openToday', SalesJourney::class) ?? false;
    }

    public function rules(): array
    {
        return ['route_id' => ['sometimes', 'nullable', 'integer', Rule::exists('distribution_routes', 'id')->where('status', 'active')]];
    }
}
