<?php

namespace App\Services\Sales;

use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use App\Services\Distribution\DailyClosingGuard;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesInvoiceService
{
    public function confirm(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice): SalesInvoice {
            $invoice->loadMissing([
                'items.product',
                'warehouse',
                'vehicle',
            ]);

            if (! $invoice->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد فاتورة ليست بحالة مسودة.');
            }

            if ($invoice->items->isEmpty()) {
                throw new RuntimeException('لا يمكن اعتماد فاتورة بدون مواد.');
            }

            $this->recalculateTotals($invoice);
            $invoice->refresh();
            $invoice->loadMissing(['warehouse']);

            $this->normalizePaymentAmounts($invoice);
            $invoice->refresh();
            $invoice->loadMissing(['warehouse']);

            $this->validateInvoiceScope($invoice);
            $this->validatePaymentAmounts($invoice);
            app(DailyClosingGuard::class)->ensureOpen($invoice->invoice_date, $invoice->warehouse_id);

            $inventory = app(InventoryMovementService::class);

            foreach ($invoice->items as $item) {
                $inventory->removeStock(
                    warehouse: $invoice->warehouse,
                    product: $item->product,
                    quantity: $item->quantity,
                    batchNumber: $item->batch_number,
                    expiryDate: $item->expiry_date?->toDateString(),
                    unitCost: 0,
                    movementType: 'sales_invoice',
                    notes: 'فاتورة بيع رقم '.$invoice->invoice_number,
                    reference: $invoice,
                );
            }

            $invoice->forceFill([
                'status' => 'confirmed',
                'invoice_cash_amount' => $invoice->paid_amount,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ])->save();

            return $invoice;
        });
    }

    public function cancel(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice): SalesInvoice {
            $invoice->loadMissing([
                'items.product',
                'warehouse',
            ]);

            if (! $invoice->isConfirmed()) {
                throw new RuntimeException('لا يمكن إلغاء فاتورة غير معتمدة.');
            }

            $confirmedPayments = CustomerPayment::query()
                ->where('sales_invoice_id', $invoice->id)
                ->where('status', 'confirmed')
                ->exists();

            if ($confirmedPayments) {
                throw new RuntimeException('لا يمكن إلغاء الفاتورة قبل إلغاء التحصيلات المرتبطة بها.');
            }

            app(DailyClosingGuard::class)->ensureOpen($invoice->invoice_date, $invoice->warehouse_id);

            $inventory = app(InventoryMovementService::class);

            foreach ($invoice->items as $item) {
                $inventory->addStock(
                    warehouse: $invoice->warehouse,
                    product: $item->product,
                    quantity: $item->quantity,
                    batchNumber: $item->batch_number,
                    expiryDate: $item->expiry_date?->toDateString(),
                    unitCost: 0,
                    movementType: 'sales_invoice_cancellation',
                    notes: 'إلغاء فاتورة بيع رقم '.$invoice->invoice_number,
                    reference: $invoice,
                );
            }

            $invoice->forceFill([
                'status' => 'cancelled',
                'paid_amount' => 0,
                'invoice_cash_amount' => 0,
                'remaining_amount' => 0,
            ])->save();

            return $invoice;
        });
    }

    public function recalculateTotals(SalesInvoice $invoice): void
    {
        $subtotal = (float) $invoice->items()->sum('line_total');
        $discount = (float) $invoice->discount_amount;
        $tax = (float) $invoice->tax_amount;
        $total = max($subtotal - $discount + $tax, 0);
        $paid = (float) $invoice->paid_amount;

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'total_amount' => $total,
            'remaining_amount' => max($total - $paid, 0),
        ])->save();
    }

    public function generateInvoiceNumber(): string
    {
        return app(DocumentNumberService::class)->next('sales_invoice', 'INV');
    }

    private function validateInvoiceScope(SalesInvoice $invoice): void
    {
        if (! $invoice->vehicle_id) {
            return;
        }

        if ($invoice->warehouse?->type !== 'vehicle') {
            throw new RuntimeException('فاتورة السيارة يجب أن تصرف من مستودع سيارة.');
        }

        if ((int) $invoice->warehouse?->vehicle_id !== (int) $invoice->vehicle_id) {
            throw new RuntimeException('مستودع البيع لا يتبع السيارة المحددة في الفاتورة.');
        }
    }

    private function normalizePaymentAmounts(SalesInvoice $invoice): void
    {
        $total = (float) $invoice->total_amount;

        if ($invoice->payment_type === 'cash') {
            $invoice->forceFill([
                'paid_amount' => $total,
                'remaining_amount' => 0,
            ])->save();

            return;
        }

        if ($invoice->payment_type === 'credit') {
            $invoice->forceFill([
                'paid_amount' => 0,
                'remaining_amount' => $total,
            ])->save();
        }
    }

    private function validatePaymentAmounts(SalesInvoice $invoice): void
    {
        $total = (float) $invoice->total_amount;
        $paid = (float) $invoice->paid_amount;

        if ($paid < 0) {
            throw new RuntimeException('المبلغ المدفوع لا يمكن أن يكون سالباً.');
        }

        if ($paid > $total) {
            throw new RuntimeException('المبلغ المدفوع أكبر من إجمالي الفاتورة.');
        }

        if ($invoice->payment_type === 'cash' && abs($paid - $total) > 0.0001) {
            throw new RuntimeException('الفاتورة النقدية يجب أن تكون مسددة بالكامل.');
        }

        if ($invoice->payment_type === 'credit' && $paid > 0) {
            throw new RuntimeException('الفاتورة الآجلة لا يجب أن تحتوي مبلغاً مدفوعاً عند الإنشاء.');
        }

        if ($invoice->payment_type === 'partial' && ($paid <= 0 || $paid >= $total)) {
            throw new RuntimeException('الفاتورة الجزئية يجب أن تحتوي دفعة أكبر من الصفر وأقل من الإجمالي.');
        }
    }
}
