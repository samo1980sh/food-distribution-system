<?php

namespace App\Services\Sales;

use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerPaymentService
{
    public function confirm(CustomerPayment $payment): CustomerPayment
    {
        return DB::transaction(function () use ($payment): CustomerPayment {
            $payment = CustomerPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if (! $payment->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد تحصيل ليس بحالة مسودة.');
            }

            if ((float) $payment->amount <= 0) {
                throw new RuntimeException('قيمة التحصيل يجب أن تكون أكبر من الصفر.');
            }

            if ($payment->sales_invoice_id) {
                $this->applyToInvoice($payment->sales_invoice_id, (float) $payment->amount);
            }

            DB::table('customer_payments')
                ->where('id', $payment->id)
                ->update([
                    'status' => 'confirmed',
                    'confirmed_by' => Auth::id(),
                    'confirmed_at' => now(),
                    'updated_at' => now(),
                ]);

            return $payment->refresh();
        });
    }

    private function applyToInvoice(int $invoiceId, float $amount): void
    {
        $invoice = SalesInvoice::query()
            ->lockForUpdate()
            ->findOrFail($invoiceId);

        if (! $invoice->isConfirmed()) {
            throw new RuntimeException('لا يمكن تسجيل تحصيل على فاتورة غير معتمدة.');
        }

        $totalAmount = (float) $invoice->total_amount;
        $paidAmount = (float) $invoice->paid_amount;
        $remainingAmount = (float) $invoice->remaining_amount;

        if ($remainingAmount <= 0) {
            throw new RuntimeException('هذه الفاتورة مسددة بالكامل.');
        }

        if ($amount > $remainingAmount) {
            throw new RuntimeException('قيمة التحصيل أكبر من المبلغ المتبقي على الفاتورة.');
        }

        $newPaidAmount = $paidAmount + $amount;
        $newRemainingAmount = max($totalAmount - $newPaidAmount, 0);

        DB::table('sales_invoices')
            ->where('id', $invoice->id)
            ->update([
                'paid_amount' => $newPaidAmount,
                'remaining_amount' => $newRemainingAmount,
                'updated_at' => now(),
            ]);
    }

    public function generatePaymentNumber(): string
    {
        $date = now()->format('Ymd');

        $count = CustomerPayment::query()
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return 'PAY-' . $date . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}