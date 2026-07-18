<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Enums\UserRole;
use App\Rules\ActiveEmployeeForOperationalRole;
use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomerPaymentWriteRequest extends OperationalWriteRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()?->can('create', CustomerPayment::class) ?? false;
        }

        $payment = $this->route('customerPayment');

        return $payment instanceof CustomerPayment
            && ($this->user()?->can('update', $payment) ?? false);
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
            'warehouse_id' => ['sometimes', 'nullable', 'integer', Rule::exists('warehouses', 'id')->where('status', 'active')],
            'sales_representative_id' => [
                'sometimes',
                'nullable',
                'integer',
                new ActiveEmployeeForOperationalRole(UserRole::SALES_REPRESENTATIVE),
            ],
            'payment_date' => $this->requiredOrSometimes(['date']),
            'payment_method' => $this->requiredOrSometimes([Rule::in(['cash', 'bank_transfer', 'cheque', 'other'])]),
            'amount' => $this->requiredOrSometimes(['numeric', 'gt:0']),
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $payment = $this->route('customerPayment');
            $invoiceId = $this->input(
                'sales_invoice_id',
                $payment instanceof CustomerPayment ? $payment->sales_invoice_id : null,
            );

            if ($invoiceId === null || $invoiceId === '') {
                return;
            }

            $invoice = SalesInvoice::query()->find((int) $invoiceId);

            if ($invoice === null) {
                $validator->errors()->add(
                    'sales_invoice_id',
                    'الفاتورة المختارة غير متاحة ضمن نطاق وصولك.',
                );

                return;
            }

            if (! $invoice->isConfirmed()) {
                $validator->errors()->add(
                    'sales_invoice_id',
                    'يجب اختيار فاتورة معتمدة للتحصيل.',
                );
            }

            $customerId = $this->input(
                'customer_id',
                $payment instanceof CustomerPayment ? $payment->customer_id : null,
            );

            if ($customerId !== null && (int) $invoice->customer_id !== (int) $customerId) {
                $validator->errors()->add(
                    'sales_invoice_id',
                    'الفاتورة المختارة لا تتبع العميل المحدد.',
                );
            }
        }];
    }
}
