<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use Illuminate\Support\Collection;

class CustomerStatementService
{
    public function generate(
        int $customerId,
        string $from,
        string $until,
    ): array {
        $customer = Customer::query()
            ->with([
                'area',
                'route',
            ])
            ->findOrFail($customerId);

        $openingBalance = $this->calculateOpeningBalance(
            customerId: $customer->id,
            beforeDate: $from,
        );

        $invoiceTransactions = SalesInvoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $until)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->map(function (SalesInvoice $invoice): array {
                $paymentTypeLabel = match ($invoice->payment_type) {
                    'cash' => 'نقدي',
                    'credit' => 'آجل',
                    'partial' => 'دفعة جزئية',
                    default => $invoice->payment_type,
                };

                return [
                    'date' => $invoice->invoice_date?->format('Y-m-d') ?? '',
                    'sort_order' => 10,
                    'sort_id' => $invoice->id,
                    'type' => 'sales_invoice',
                    'type_label' => 'فاتورة بيع',
                    'document_number' => $invoice->invoice_number,
                    'description' => 'فاتورة بيع — '.$paymentTypeLabel,
                    'debit' => (float) $invoice->total_amount,
                    'credit' => (float) $invoice->invoice_cash_amount,
                    'notes' => $invoice->notes,
                ];
            });

        $paymentTransactions = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'confirmed')
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $until)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->map(function (CustomerPayment $payment): array {
                $methodLabel = match ($payment->payment_method) {
                    'cash' => 'نقدي',
                    'bank_transfer' => 'تحويل بنكي',
                    'cheque' => 'شيك',
                    'other' => 'أخرى',
                    default => $payment->payment_method,
                };

                $description = 'تحصيل عميل — '.$methodLabel;

                if (filled($payment->reference_number)) {
                    $description .= ' — مرجع: '.$payment->reference_number;
                }

                return [
                    'date' => $payment->payment_date?->format('Y-m-d') ?? '',
                    'sort_order' => 20,
                    'sort_id' => $payment->id,
                    'type' => 'customer_payment',
                    'type_label' => 'تحصيل',
                    'document_number' => $payment->payment_number,
                    'description' => $description,
                    'debit' => 0.0,
                    'credit' => (float) $payment->amount,
                    'notes' => $payment->notes,
                ];
            });

        $returnTransactions = SalesReturn::query()
            ->with('salesInvoice')
            ->where('customer_id', $customer->id)
            ->where('status', 'confirmed')
            ->whereDate('return_date', '>=', $from)
            ->whereDate('return_date', '<=', $until)
            ->orderBy('return_date')
            ->orderBy('id')
            ->get()
            ->map(function (SalesReturn $salesReturn): array {
                $description = 'مرتجع بيع';

                if ($salesReturn->salesInvoice) {
                    $description .= ' — الفاتورة '
                        .$salesReturn->salesInvoice->invoice_number;
                }

                if (filled($salesReturn->return_reason)) {
                    $description .= ' — '.$salesReturn->return_reason;
                }

                return [
                    'date' => $salesReturn->return_date?->format('Y-m-d') ?? '',
                    'sort_order' => 30,
                    'sort_id' => $salesReturn->id,
                    'type' => 'sales_return',
                    'type_label' => 'مرتجع بيع',
                    'document_number' => $salesReturn->return_number,
                    'description' => $description,
                    'debit' => 0.0,
                    'credit' => (float) $salesReturn->total_amount,
                    'notes' => $salesReturn->notes,
                ];
            });

        $transactions = $invoiceTransactions
            ->concat($paymentTransactions)
            ->concat($returnTransactions)
            ->sort(function (array $first, array $second): int {
                return [
                    $first['date'],
                    $first['sort_order'],
                    $first['sort_id'],
                ] <=> [
                    $second['date'],
                    $second['sort_order'],
                    $second['sort_id'],
                ];
            })
            ->values();

        $transactions = $this->applyRunningBalance(
            transactions: $transactions,
            openingBalance: $openingBalance,
        );

        $periodDebit = (float) $transactions->sum('debit');
        $periodCredit = (float) $transactions->sum('credit');

        return [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'owner_name' => $customer->owner_name,
                'phone' => $customer->phone,
                'mobile' => $customer->mobile,
                'address' => $customer->address,
                'area' => $customer->area?->name_ar,
                'route' => $customer->route?->name,
                'credit_limit' => (float) $customer->credit_limit,
            ],
            'transactions' => $transactions->all(),
            'totals' => [
                'opening_balance' => $openingBalance,
                'period_debit' => $periodDebit,
                'period_credit' => $periodCredit,
                'closing_balance' => $openingBalance
                    + $periodDebit
                    - $periodCredit,
                'sales_total' => (float) $invoiceTransactions->sum('debit'),
                'invoice_cash_total' => (float) $invoiceTransactions->sum('credit'),
                'payments_total' => (float) $paymentTransactions->sum('credit'),
                'returns_total' => (float) $returnTransactions->sum('credit'),
                'transaction_count' => $transactions->count(),
            ],
        ];
    }

    private function calculateOpeningBalance(
        int $customerId,
        string $beforeDate,
    ): float {
        $invoiceDebit = (float) SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', '<', $beforeDate)
            ->sum('total_amount');

        $invoiceCashCredit = (float) SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', '<', $beforeDate)
            ->sum('invoice_cash_amount');

        $paymentCredit = (float) CustomerPayment::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->whereDate('payment_date', '<', $beforeDate)
            ->sum('amount');

        $returnCredit = (float) SalesReturn::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->whereDate('return_date', '<', $beforeDate)
            ->sum('total_amount');

        return $invoiceDebit
            - $invoiceCashCredit
            - $paymentCredit
            - $returnCredit;
    }

    private function applyRunningBalance(
        Collection $transactions,
        float $openingBalance,
    ): Collection {
        $runningBalance = $openingBalance;

        return $transactions->map(
            function (array $transaction) use (&$runningBalance): array {
                $runningBalance += (float) $transaction['debit'];
                $runningBalance -= (float) $transaction['credit'];

                $transaction['balance'] = $runningBalance;

                unset(
                    $transaction['sort_order'],
                    $transaction['sort_id'],
                );

                return $transaction;
            },
        );
    }
}