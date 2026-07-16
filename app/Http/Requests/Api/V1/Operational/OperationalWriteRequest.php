<?php

namespace App\Http\Requests\Api\V1\Operational;

use Illuminate\Foundation\Http\FormRequest;

abstract class OperationalWriteRequest extends FormRequest
{
    /** @param list<mixed> $rules
     *  @return list<mixed>
     */
    protected function requiredOrSometimes(array $rules): array
    {
        return [
            $this->isMethod('post') ? 'required' : 'sometimes',
            ...$rules,
        ];
    }

    /** @return list<mixed> */
    protected function clientReferenceRules(): array
    {
        if (! $this->isMethod('post')) {
            return ['prohibited'];
        }

        return [
            'required',
            'string',
            'max:100',
            'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{5,99}$/',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'client_reference.regex' => 'مرجع العميل يجب أن يكون معرفاً ثابتاً من 6 إلى 100 محرف ويحتوي أحرفاً أو أرقاماً أو . _ : - فقط.',
            'items.*.product_id.distinct' => 'لا يجوز تكرار المنتج ضمن نفس المستند.',
        ];
    }
}
