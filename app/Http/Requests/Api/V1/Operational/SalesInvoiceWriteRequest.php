<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\SalesInvoice;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalesInvoiceWriteRequest extends OperationalWriteRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()?->can('create', SalesInvoice::class) ?? false;
        }

        $invoice = $this->route('salesInvoice');

        return $invoice instanceof SalesInvoice
            && ($this->user()?->can('update', $invoice) ?? false);
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
            'vehicle_id' => ['sometimes', 'nullable', 'integer', Rule::exists('vehicles', 'id')->where('status', 'active')],
            'route_id' => ['sometimes', 'nullable', 'integer', Rule::exists('distribution_routes', 'id')->where('status', 'active')],
            'warehouse_id' => $this->requiredOrSometimes([
                'integer',
                Rule::exists('warehouses', 'id')->where('status', 'active'),
            ]),
            'sales_representative_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where(fn ($query) => $query->where('status', 'active')->where('type', 'sales_representative'))],
            'invoice_date' => $this->requiredOrSometimes(['date']),
            'payment_type' => $this->requiredOrSometimes([Rule::in(['cash', 'credit', 'partial'])]),
            'paid_amount' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'items' => $this->requiredOrSometimes(['array', 'min:1', 'max:100']),
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.batch_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.expiry_date' => ['sometimes', 'nullable', 'date'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $keys = [];

            foreach ((array) $this->input('items', []) as $index => $item) {
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $discount = (float) ($item['discount_amount'] ?? 0);
                $key = implode('|', [
                    (int) ($item['product_id'] ?? 0),
                    trim((string) ($item['batch_number'] ?? '')),
                    (string) ($item['expiry_date'] ?? ''),
                ]);

                if (isset($keys[$key])) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'لا يجوز تكرار المنتج مع نفس التشغيلة والصلاحية ضمن الفاتورة.',
                    );
                }

                $keys[$key] = true;

                if ($discount - ($quantity * $unitPrice) > 0.0001) {
                    $validator->errors()->add(
                        "items.{$index}.discount_amount",
                        'حسم المادة لا يمكن أن يتجاوز قيمة السطر.',
                    );
                }
            }
        }];
    }
}
