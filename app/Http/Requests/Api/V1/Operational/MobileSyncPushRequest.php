<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Support\Api\MobileSyncPushRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MobileSyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'context_key' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/',
            ],
            'batch_id' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{5,99}$/',
            ],
            'operations' => [
                'required',
                'array',
                'min:1',
                'max:'.(int) config('mobile_api.sync_max_push_operations', 50),
            ],
            'operations.*.operation_id' => [
                'required',
                'string',
                'distinct',
                'max:100',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{5,99}$/',
            ],
            'operations.*.entity' => [
                'required',
                'string',
                Rule::in(MobileSyncPushRegistry::entities()),
            ],
            'operations.*.action' => [
                'required',
                'string',
                Rule::in(MobileSyncPushRegistry::actions()),
            ],
            'operations.*.record_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'operations.*.base_version' => ['sometimes', 'nullable', 'string', 'regex:/^c:[1-9][0-9]*$/'],
            'operations.*.payload' => ['sometimes', 'array'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $maxOperationBytes = (int) config('mobile_api.sync_max_push_operation_kb', 256) * 1024;

            foreach ((array) $this->input('operations', []) as $index => $operation) {
                $entity = (string) ($operation['entity'] ?? '');
                $action = (string) ($operation['action'] ?? '');
                $recordId = $operation['record_id'] ?? null;
                $baseVersion = $operation['base_version'] ?? null;

                if ($entity !== '' && $action !== '' && ! MobileSyncPushRegistry::supports($entity, $action)) {
                    $validator->errors()->add(
                        "operations.{$index}.action",
                        'العملية المطلوبة غير مدعومة لهذا النوع من السجلات.',
                    );
                }

                if ($action === 'create') {
                    if ($recordId !== null && $recordId !== '') {
                        $validator->errors()->add(
                            "operations.{$index}.record_id",
                            'record_id غير مسموح في عملية الإنشاء.',
                        );
                    }

                    if ($baseVersion !== null && $baseVersion !== '') {
                        $validator->errors()->add(
                            "operations.{$index}.base_version",
                            'base_version غير مسموح في عملية الإنشاء.',
                        );
                    }
                } elseif ($action !== '') {
                    if ($recordId === null || $recordId === '') {
                        $validator->errors()->add(
                            "operations.{$index}.record_id",
                            'record_id مطلوب لهذه العملية.',
                        );
                    }

                    if ($baseVersion === null || $baseVersion === '') {
                        $validator->errors()->add(
                            "operations.{$index}.base_version",
                            'base_version مطلوب لحماية السجل من التعارض.',
                        );
                    }
                }

                $encoded = json_encode(
                    $operation,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );

                if (is_string($encoded) && strlen($encoded) > $maxOperationBytes) {
                    $validator->errors()->add(
                        "operations.{$index}",
                        'حجم عملية المزامنة يتجاوز الحد المسموح.',
                    );
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'batch_id.regex' => 'batch_id يجب أن يكون معرفاً ثابتاً من 6 إلى 100 محرف.',
            'operations.*.operation_id.regex' => 'operation_id يجب أن يكون معرفاً ثابتاً من 6 إلى 100 محرف.',
            'operations.*.operation_id.distinct' => 'لا يجوز تكرار operation_id ضمن الدفعة نفسها.',
        ];
    }

    public function contextKey(): string
    {
        return (string) $this->validated('context_key');
    }

    public function batchId(): string
    {
        return (string) $this->validated('batch_id');
    }

    /** @return list<array<string, mixed>> */
    public function operations(): array
    {
        return array_values((array) $this->validated('operations'));
    }
}
