<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\DailyClosing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SubmitDailyClosingCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        $closing = $this->route('dailyClosing');

        return $closing instanceof DailyClosing
            && Gate::allows('submitCash', $closing);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'actual_cash_amount' => ['required', 'numeric', 'min:0'],
            'cash_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
