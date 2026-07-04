<?php

namespace App\Services\Sales;

use App\Models\SalesInvoice;
use App\Services\Inventory\InventoryMovementService;
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
            ]);

            if (! $invoice->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد فاتورة ليست بحالة مسودة.');
            }

            if ($invoice->items->isEmpty()) {
                throw new RuntimeException('لا يمكن اعتماد فاتورة بدون مواد.');
            }

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
                    notes: 'فاتورة بيع رقم ' . $invoice->invoice_number,
                    reference: $invoice,
                );
            }

            $invoice->forceFill([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
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
        $date = now()->format('Ymd');

        $count = SalesInvoice::query()
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return 'INV-' . $date . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}