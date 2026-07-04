<?php

namespace App\Services\Sales;

use App\Models\SalesReturn;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesReturnService
{
    public function confirm(SalesReturn $salesReturn): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn): SalesReturn {
            $salesReturn->loadMissing([
                'items.product',
                'warehouse',
            ]);

            if (! $salesReturn->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد مرتجع ليس بحالة مسودة.');
            }

            if ($salesReturn->items->isEmpty()) {
                throw new RuntimeException('لا يمكن اعتماد مرتجع بدون مواد.');
            }

            $inventory = app(InventoryMovementService::class);

            foreach ($salesReturn->items as $item) {
                $inventory->addStock(
                    warehouse: $salesReturn->warehouse,
                    product: $item->product,
                    quantity: $item->quantity,
                    batchNumber: $item->batch_number,
                    expiryDate: $item->expiry_date?->toDateString(),
                    unitCost: 0,
                    movementType: 'sales_return',
                    notes: 'مرتجع بيع رقم ' . $salesReturn->return_number,
                    reference: $salesReturn,
                );
            }

            $salesReturn->forceFill([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ])->save();

            return $salesReturn;
        });
    }

    public function recalculateTotals(SalesReturn $salesReturn): void
    {
        $subtotal = (float) $salesReturn->items()->sum('line_total');
        $discount = (float) $salesReturn->discount_amount;

        $salesReturn->forceFill([
            'subtotal' => $subtotal,
            'total_amount' => max($subtotal - $discount, 0),
        ])->save();
    }

    public function generateReturnNumber(): string
    {
        $date = now()->format('Ymd');

        $count = SalesReturn::query()
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return 'SRT-' . $date . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}