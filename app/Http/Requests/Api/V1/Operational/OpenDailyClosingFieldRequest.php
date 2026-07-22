<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\DailyClosing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OpenDailyClosingFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('openField', DailyClosing::class);
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
