<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\SalesJourney;
use Illuminate\Foundation\Http\FormRequest;

class FinishSalesJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $journey = $this->route('salesJourney');
        return $journey instanceof SalesJourney && ($this->user()?->can('finish', $journey) ?? false);
    }

    public function rules(): array
    {
        return ['notes' => ['sometimes', 'nullable', 'string', 'max:5000']];
    }
}
