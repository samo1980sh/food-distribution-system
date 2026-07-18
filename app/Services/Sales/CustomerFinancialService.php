<?php

namespace App\Services\Sales;

use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class CustomerFinancialService
{
    public const DEFAULT_CREDIT_DAYS = 30;

    public function normalizeInvoiceTerms(SalesInvoice $invoice): void
    {
        if (blank($invoice->invoice_date)) {
            return;
        }

        $invoiceDate = Carbon::parse($invoice->invoice_date)->startOfDay();

        if ($invoice->payment_type === 'cash') {
            $invoice->due_date = $invoiceDate->toDateString();

            return;
        }

        if (blank($invoice->due_date)) {
            $creditDays = $this->creditDaysForCustomer($invoice->customer_id);
            $invoice->due_date = $invoiceDate
                ->copy()
                ->addDays($creditDays)
                ->toDateString();
        }

        $dueDate = Carbon::parse($invoice->due_date)->startOfDay();

        if ($dueDate->lt($invoiceDate)) {
            throw new RuntimeException('تاريخ استحقاق الفاتورة لا يمكن أن يسبق تاريخ الفاتورة.');
        }
    }

    public function customerBalance(Customer|int $customer): float
    {
        $customerId = $customer instanceof Customer
            ? (int) $customer->getKey()
            : $customer;

        $invoiceDebit = (float) SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->sum('total_amount');

        $invoiceCash = (float) SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->sum('invoice_cash_amount');

        $payments = (float) CustomerPayment::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->sum('amount');

        $returns = (float) SalesReturn::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->sum('total_amount');

        return round(max(
            $invoiceDebit - $invoiceCash - $payments - $returns,
            0,
        ), 2);
    }

    public function enforceCreditLimit(
        SalesInvoice $invoice,
        ?User $actor,
    ): void {
        $customer = Customer::query()
            ->lockForUpdate()
            ->findOrFail($invoice->customer_id);

        $before = $this->customerBalance($customer);
        $additionalExposure = max((float) $invoice->remaining_amount, 0);
        $after = round($before + $additionalExposure, 2);
        $creditLimit = round(max((float) $customer->credit_limit, 0), 2);

        $invoice->forceFill([
            'credit_limit_snapshot' => $creditLimit,
            'credit_exposure_before' => $before,
            'credit_exposure_after' => $after,
        ]);

        if ($additionalExposure <= 0 || $creditLimit <= 0 || $after - $creditLimit <= 0.0001) {
            $invoice->forceFill([
                'credit_limit_override_requested' => false,
                'credit_limit_overridden' => false,
                'credit_limit_override_reason' => null,
                'credit_limit_overridden_by' => null,
                'credit_limit_overridden_at' => null,
            ]);

            return;
        }

        if (! $invoice->credit_limit_override_requested) {
            throw new RuntimeException(sprintf(
                'اعتماد الفاتورة سيرفع مديونية العميل إلى %.2f بينما حد الائتمان %.2f. يلزم استثناء ائتماني معتمد.',
                $after,
                $creditLimit,
            ));
        }

        if (! $actor?->can(PermissionName::SALES_INVOICES_OVERRIDE_CREDIT_LIMIT->value)) {
            throw new RuntimeException('لا تملك صلاحية تجاوز حد ائتمان العميل.');
        }

        $reason = trim((string) $invoice->credit_limit_override_reason);

        if (mb_strlen($reason) < 10) {
            throw new RuntimeException('يجب إدخال سبب واضح لتجاوز حد الائتمان لا يقل عن 10 أحرف.');
        }

        $invoice->forceFill([
            'credit_limit_overridden' => true,
            'credit_limit_override_reason' => $reason,
            'credit_limit_overridden_by' => $actor->getKey(),
            'credit_limit_overridden_at' => now(),
        ]);
    }

    public function creditDaysForCustomer(Customer|int|null $customer): int
    {
        $days = $customer instanceof Customer
            ? (int) $customer->credit_days
            : ($customer
                ? (int) Customer::query()->whereKey($customer)->value('credit_days')
                : self::DEFAULT_CREDIT_DAYS);

        return min(max($days ?: self::DEFAULT_CREDIT_DAYS, 1), 365);
    }
}
