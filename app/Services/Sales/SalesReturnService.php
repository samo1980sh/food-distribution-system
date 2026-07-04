<?php

namespace App\Services\Sales;

use App\Models\SalesReturn;
use App\Services\Distribution\DailyClosingGuard;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Support\DocumentNumberService;
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
                'salesInvoice',
            ]);

            if (! $salesReturn->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد مرتجع ليس بحالة مسودة.');
            }

            if ($salesReturn->items->isEmpty()) {
                throw new RuntimeException('لا يمكن اعتماد مرتجع بدون مواد.');
            }

            $this->validateReturnScope($salesReturn);
            $this->validateAgainstOriginalInvoice($salesReturn);
            app(DailyClosingGuard::class)->ensureOpen($salesReturn->return_date, $salesReturn->warehouse_id);

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
                    notes: 'مرتجع بيع رقم '.$salesReturn->return_number,
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

    public function cancel(SalesReturn $salesReturn): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn): SalesReturn {
            $salesReturn->loadMissing([
                'items.product',
                'warehouse',
            ]);

            if (! $salesReturn->isConfirmed()) {
                throw new RuntimeException('لا يمكن إلغاء مرتجع غير معتمد.');
            }

            app(DailyClosingGuard::class)->ensureOpen($salesReturn->return_date, $salesReturn->warehouse_id);

            $inventory = app(InventoryMovementService::class);

            foreach ($salesReturn->items as $item) {
                $inventory->removeStock(
                    warehouse: $salesReturn->warehouse,
                    product: $item->product,
                    quantity: $item->quantity,
                    batchNumber: $item->batch_number,
                    expiryDate: $item->expiry_date?->toDateString(),
                    unitCost: 0,
                    movementType: 'sales_return_cancellation',
                    notes: 'إلغاء مرتجع بيع رقم '.$salesReturn->return_number,
                    reference: $salesReturn,
                );
            }

            $salesReturn->forceFill([
                'status' => 'cancelled',
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
        return app(DocumentNumberService::class)->next('sales_return', 'SRT');
    }

    private function validateReturnScope(SalesReturn $salesReturn): void
    {
        if ($salesReturn->salesInvoice && (int) $salesReturn->salesInvoice->customer_id !== (int) $salesReturn->customer_id) {
            throw new RuntimeException('الفاتورة الأصلية لا تتبع العميل المحدد في المرتجع.');
        }

        if (! $salesReturn->vehicle_id) {
            return;
        }

        if ($salesReturn->warehouse?->type !== 'vehicle') {
            throw new RuntimeException('مرتجع السيارة يجب أن يعود إلى مستودع سيارة.');
        }

        if ((int) $salesReturn->warehouse?->vehicle_id !== (int) $salesReturn->vehicle_id) {
            throw new RuntimeException('مستودع المرتجع لا يتبع السيارة المحددة.');
        }
    }

    private function validateAgainstOriginalInvoice(SalesReturn $salesReturn): void
    {
        if (! $salesReturn->sales_invoice_id) {
            return;
        }

        if (! $salesReturn->salesInvoice?->isConfirmed()) {
            throw new RuntimeException('لا يمكن اعتماد مرتجع مرتبط بفاتورة غير معتمدة.');
        }

        $soldQuantities = DB::table('sales_invoice_items')
            ->where('sales_invoice_id', $salesReturn->sales_invoice_id)
            ->selectRaw($this->itemKeyExpression().' as item_key, SUM(quantity) as quantity')
            ->groupBy('item_key')
            ->pluck('quantity', 'item_key')
            ->map(fn ($quantity): float => (float) $quantity);

        $previousReturnQuantities = DB::table('sales_return_items')
            ->join('sales_returns', 'sales_return_items.sales_return_id', '=', 'sales_returns.id')
            ->where('sales_returns.sales_invoice_id', $salesReturn->sales_invoice_id)
            ->where('sales_returns.status', 'confirmed')
            ->where('sales_returns.id', '!=', $salesReturn->id)
            ->selectRaw($this->prefixedItemKeyExpression('sales_return_items').' as item_key, SUM(sales_return_items.quantity) as quantity')
            ->groupBy('item_key')
            ->pluck('quantity', 'item_key')
            ->map(fn ($quantity): float => (float) $quantity);

        $currentReturnQuantities = $salesReturn->items
            ->groupBy(fn ($item): string => $this->itemKey(
                (int) $item->product_id,
                $item->batch_number,
                $item->expiry_date?->toDateString(),
            ))
            ->map(fn ($items): float => $items->sum(fn ($item): float => (float) $item->quantity));

        foreach ($currentReturnQuantities as $itemKey => $returnQuantity) {
            $soldQuantity = (float) ($soldQuantities[$itemKey] ?? 0);

            if ($soldQuantity <= 0) {
                throw new RuntimeException('يوجد منتج في المرتجع غير موجود ضمن الفاتورة الأصلية.');
            }

            $previousReturned = (float) ($previousReturnQuantities[$itemKey] ?? 0);
            $availableQuantity = $soldQuantity - $previousReturned;

            if ($returnQuantity > $availableQuantity) {
                throw new RuntimeException('كمية المرتجع أكبر من الكمية المتاحة للإرجاع في الفاتورة الأصلية.');
            }
        }
    }

    private function itemKey(int $productId, ?string $batchNumber, ?string $expiryDate): string
    {
        return implode('|', [
            $productId,
            trim((string) $batchNumber),
            $expiryDate ?? '',
        ]);
    }

    private function itemKeyExpression(): string
    {
        return "CONCAT(product_id, '|', COALESCE(batch_number, ''), '|', COALESCE(DATE(expiry_date), ''))";
    }

    private function prefixedItemKeyExpression(string $table): string
    {
        return "CONCAT({$table}.product_id, '|', COALESCE({$table}.batch_number, ''), '|', COALESCE(DATE({$table}.expiry_date), ''))";
    }
}
