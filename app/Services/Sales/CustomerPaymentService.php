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
            $payment->loadMissing(['salesInvoice']);

            if (! $payment->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد تحصيل ليس بحالة مسودة.');
            }

            if ((float) $payment->amount <= 0) {
                throw new RuntimeException('قيمة التحصيل يجب أن تكون أكبر من الصفر.');
            }

            if ($payment->salesInvoice) {
                $this->applyToInvoice($payment->salesInvoice, (float) $payment->amount);
            }

            $payment->forceFill([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ])->save();

            return $payment;
        });
    }

    private function applyToInvoice(SalesInvoice $invoice, float $amount): void
    {
        if (! $invoice->isConfirmed()) {
            throw new RuntimeException('لا يمكن تسجيل تحصيل على فاتورة غير معتمدة.');
        }

        $newPaidAmount = (float) $invoice->paid_amount + $amount;

        if ($newPaidAmount > (float) $invoice->total_amount) {
            throw new RuntimeException('قيمة التحصيل أكبر من المبلغ المتبقي على الفاتورة.');
        }

        $invoice->forceFill([
            'paid_amount' => $newPaidAmount,
            'remaining_amount' => max((float) $invoice->total_amount - $newPaidAmount, 0),
        ])->save();
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