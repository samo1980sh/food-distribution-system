<?php

namespace App\Services\Sales;

use App\Models\CustomerPayment;
use App\Services\Distribution\DailyClosingGuard;
use App\Services\Support\DocumentNumberService;
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

            $scope = [
                'vehicle_id' => $payment->vehicle_id,
                'route_id' => $payment->route_id,
                'warehouse_id' => $payment->warehouse_id,
                'sales_representative_id' => $payment->sales_representative_id,
            ];

            if ($payment->sales_invoice_id) {
                $scope = $this->applyToInvoice($payment, (float) $payment->amount);
            } else {
                $this->validateStandalonePaymentScope($payment);
            }

            $this->validatePaymentScope($scope);
            app(DailyClosingGuard::class)->ensureOpen($payment->payment_date, (int) $scope['warehouse_id']);

            $payment->forceFill($scope + [
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ])->save();

            if ($payment->sales_invoice_id) {
                app(SalesInvoiceService::class)->refreshFinancialBalance($payment->sales_invoice_id);
            }

            return $payment->refresh();
        });
    }

    public function cancel(CustomerPayment $payment): CustomerPayment
    {
        return DB::transaction(function () use ($payment): CustomerPayment {
            $payment = CustomerPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if (! $payment->isConfirmed()) {
                throw new RuntimeException('لا يمكن إلغاء تحصيل غير معتمد.');
            }

            if (! $payment->warehouse_id) {
                throw new RuntimeException('لا يمكن إلغاء تحصيل لا يحتوي مستودعاً.');
            }

            app(DailyClosingGuard::class)->ensureOpen($payment->payment_date, $payment->warehouse_id);

            $payment->forceFill([
                'status' => 'cancelled',
            ])->save();

            if ($payment->sales_invoice_id) {
                app(SalesInvoiceService::class)->refreshFinancialBalance($payment->sales_invoice_id);
            }

            return $payment->refresh();
        });
    }

    private function applyToInvoice(CustomerPayment $payment, float $amount): array
    {
        $invoice = app(SalesInvoiceService::class)->refreshFinancialBalance($payment->sales_invoice_id);

        if ((int) $invoice->customer_id !== (int) $payment->customer_id) {
            throw new RuntimeException('الفاتورة المختارة لا تتبع العميل المحدد في التحصيل.');
        }

        if (! $invoice->isConfirmed()) {
            throw new RuntimeException('لا يمكن تسجيل تحصيل على فاتورة غير معتمدة.');
        }

        $remainingAmount = (float) $invoice->remaining_amount;

        if ($remainingAmount <= 0) {
            throw new RuntimeException('هذه الفاتورة مسددة بالكامل.');
        }

        if ($amount > $remainingAmount) {
            throw new RuntimeException('قيمة التحصيل أكبر من المبلغ المتبقي على الفاتورة.');
        }

        return [
            'vehicle_id' => $payment->vehicle_id ?: $invoice->vehicle_id,
            'route_id' => $payment->route_id ?: $invoice->route_id,
            'warehouse_id' => $payment->warehouse_id ?: $invoice->warehouse_id,
            'sales_representative_id' => $payment->sales_representative_id ?: $invoice->sales_representative_id,
        ];
    }

    private function validateStandalonePaymentScope(CustomerPayment $payment): void
    {
        if (! $payment->warehouse_id) {
            throw new RuntimeException('التحصيل غير المرتبط بفاتورة يجب أن يحدد المستودع حتى يظهر في الإغلاق اليومي الصحيح.');
        }

        $this->validatePaymentScope([
            'vehicle_id' => $payment->vehicle_id,
            'warehouse_id' => $payment->warehouse_id,
        ]);
    }

    private function validatePaymentScope(array $scope): void
    {
        if (empty($scope['warehouse_id'])) {
            throw new RuntimeException('يجب تحديد مستودع التحصيل.');
        }

        if (empty($scope['vehicle_id'])) {
            return;
        }

        $warehouse = DB::table('warehouses')
            ->where('id', $scope['warehouse_id'])
            ->first();

        if ($warehouse?->type !== 'vehicle') {
            throw new RuntimeException('تحصيل السيارة يجب أن يرتبط بمستودع سيارة.');
        }

        if ((int) $warehouse->vehicle_id !== (int) $scope['vehicle_id']) {
            throw new RuntimeException('مستودع التحصيل لا يتبع السيارة المحددة.');
        }
    }

    public function generatePaymentNumber(): string
    {
        return app(DocumentNumberService::class)->next('customer_payment', 'PAY');
    }
}
