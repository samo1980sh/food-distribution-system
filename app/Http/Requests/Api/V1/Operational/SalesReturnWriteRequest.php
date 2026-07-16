<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalesReturnWriteRequest extends OperationalWriteRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()?->can('create', SalesReturn::class) ?? false;
        }

        $salesReturn = $this->route('salesReturn');

        return $salesReturn instanceof SalesReturn
            && ($this->user()?->can('update', $salesReturn) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_reference' => $this->clientReferenceRules(),
            'customer_id' => $this->requiredOrSometimes([
                'integer',
                Rule::exists('customers', 'id')->where('status', 'active'),
            ]),
            'sales_invoice_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sales_invoices', 'id')],
            'vehicle_id' => ['sometimes', 'nullable', 'integer', Rule::exists('vehicles', 'id')->where('status', 'active')],
            'route_id' => ['sometimes', 'nullable', 'integer', Rule::exists('distribution_routes', 'id')->where('status', 'active')],
            'warehouse_id' => $this->requiredOrSometimes([
                'integer',
                Rule::exists('warehouses', 'id')->where('status', 'active'),
            ]),
            'sales_representative_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where(fn ($query) => $query->where('status', 'active')->where('type', 'sales_representative'))],
            'return_date' => $this->requiredOrSometimes(['date']),
            'return_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'items' => $this->requiredOrSometimes(['array', 'min:1', 'max:100']),
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.batch_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.expiry_date' => ['sometimes', 'nullable', 'date'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }


    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $salesReturn = $this->route('salesReturn');
            $invoiceId = $this->input(
                'sales_invoice_id',
                $salesReturn instanceof SalesReturn ? $salesReturn->sales_invoice_id : null,
            );
            $customerId = $this->input(
                'customer_id',
                $salesReturn instanceof SalesReturn ? $salesReturn->customer_id : null,
            );

            if ($invoiceId !== null && $invoiceId !== '') {
                $invoice = SalesInvoice::query()->find((int) $invoiceId);

                if ($invoice === null) {
                    $validator->errors()->add(
                        'sales_invoice_id',
                        'الفاتورة الأصلية غير متاحة ضمن نطاق وصولك.',
                    );
                } else {
                    if (! $invoice->isConfirmed()) {
                        $validator->errors()->add(
                            'sales_invoice_id',
                            'يجب اختيار فاتورة معتمدة للمرتجع.',
                        );
                    }

                    if ($customerId !== null && (int) $invoice->customer_id !== (int) $customerId) {
                        $validator->errors()->add(
                            'sales_invoice_id',
                            'الفاتورة الأصلية لا تتبع العميل المحدد.',
                        );
                    }
                }
            }

            $keys = [];
            $subtotal = 0.0;

            foreach ((array) $this->input('items', []) as $index => $item) {
                $key = implode('|', [
                    (int) ($item['product_id'] ?? 0),
                    trim((string) ($item['batch_number'] ?? '')),
                    (string) ($item['expiry_date'] ?? ''),
                ]);

                if (isset($keys[$key])) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'لا يجوز تكرار المنتج مع نفس التشغيلة والصلاحية ضمن المرتجع.',
                    );
                }

                $keys[$key] = true;
                $subtotal += (float) ($item['quantity'] ?? 0)
                    * (float) ($item['unit_price'] ?? 0);
            }

            if ((float) $this->input('discount_amount', 0) - $subtotal > 0.0001) {
                $validator->errors()->add(
                    'discount_amount',
                    'حسم المرتجع لا يمكن أن يتجاوز قيمته الإجمالية.',
                );
            }
        }];
    }
}
