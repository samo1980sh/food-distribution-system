<?php

namespace App\Http\Requests\Api\V1\Operational;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class CancelOperationalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        foreach ([
            'salesInvoice',
            'salesReturn',
            'customerPayment',
            'dailyClosing',
        ] as $parameter) {
            $record = $this->route($parameter);

            if ($record instanceof Model) {
                return $this->user()?->can('cancel', $record) ?? false;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge([
                'reason' => trim($this->input('reason')),
            ]);
        }
    }
}
