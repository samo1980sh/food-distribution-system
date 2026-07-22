<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\DailyClosing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SubmitDailyClosingInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $closing = $this->route('dailyClosing');

        return $closing instanceof DailyClosing
            && Gate::allows('submitInventory', $closing);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'max:500'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id'),
            ],
            'items.*.actual_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
