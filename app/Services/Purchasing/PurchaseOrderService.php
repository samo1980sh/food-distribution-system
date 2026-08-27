<?php

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use App\Services\Inventory\WarehouseReplenishmentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderService
{
    public function approve(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order): PurchaseOrder {
            $locked = PurchaseOrder::query()
                ->with(['supplier', 'warehouse'])
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if (! $locked->isDraft()) {
                throw new RuntimeException('يمكن اعتماد أمر الشراء عندما يكون مسودة فقط.');
            }

            $this->ensureSupplierActive($locked->supplier);
            $this->ensureWarehouseAllowed($locked->warehouse);

            $items = PurchaseOrderItem::query()
                ->with('product')
                ->where('purchase_order_id', $locked->id)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('لا يمكن اعتماد أمر شراء بدون أصناف.');
            }

            $this->validateOrderItems($items);

            $total = round((float) $items->sum('line_total'), 2);

            $locked->forceFill([
                'subtotal' => $total,
                'total_amount' => $total,
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * @param list<array{
     *     purchase_order_item_id:int|string,
     *     quantity:float|int|string,
     *     batch_number?:string|null,
     *     expiry_date?:string|null
     * }> $lines
     */
    public function receive(
        PurchaseOrder $order,
        array $lines,
        ?string $receiptDate = null,
        ?string $notes = null,
    ): PurchaseReceipt {
        return DB::transaction(function () use ($order, $lines, $receiptDate, $notes): PurchaseReceipt {
            $locked = PurchaseOrder::query()
                ->with(['supplier', 'warehouse'])
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if (! $locked->canReceive()) {
                throw new RuntimeException('أمر الشراء غير جاهز للاستلام.');
            }

            $this->ensureSupplierActive($locked->supplier);
            $this->ensureWarehouseAllowed($locked->warehouse);

            $normalizedLines = collect($lines)
                ->filter(fn (array $line): bool => (float) ($line['quantity'] ?? 0) > 0)
                ->values();

            if ($normalizedLines->isEmpty()) {
                throw new RuntimeException('أدخل كمية مستلمة واحدة على الأقل.');
            }

            $receipt = PurchaseReceipt::query()->create([
                'purchase_order_id' => $locked->id,
                'warehouse_id' => $locked->warehouse_id,
                'receipt_date' => $receiptDate ?: now()->toDateString(),
                'status' => 'posted',
                'total_amount' => 0,
                'notes' => $this->normalizeOptionalText($notes),
                'created_by' => Auth::id(),
            ]);

            $receiptTotal = 0.0;
            $usedItemIds = [];

            foreach ($normalizedLines as $line) {
                $itemId = (int) ($line['purchase_order_item_id'] ?? 0);

                if ($itemId <= 0 || in_array($itemId, $usedItemIds, true)) {
                    throw new RuntimeException('سطر الاستلام غير صالح أو مكرر.');
                }

                $usedItemIds[] = $itemId;

                $item = PurchaseOrderItem::query()
                    ->with('product')
                    ->where('purchase_order_id', $locked->id)
                    ->whereKey($itemId)
                    ->lockForUpdate()
                    ->first();

                if (! $item instanceof PurchaseOrderItem) {
                    throw new RuntimeException('أحد أصناف الاستلام لا يتبع أمر الشراء المحدد.');
                }

                $quantity = round((float) ($line['quantity'] ?? 0), 3);
                $remaining = round(
                    (float) $item->ordered_quantity - (float) $item->received_quantity,
                    3,
                );

                if ($quantity <= 0 || $quantity - $remaining > 0.0001) {
                    throw new RuntimeException(
                        'الكمية المستلمة للصنف '.$item->product?->name_ar.' تتجاوز الكمية المتبقية.',
                    );
                }

                $movement = app(WarehouseReplenishmentService::class)->receive(
                    warehouse: $locked->warehouse,
                    product: $item->product,
                    quantity: $quantity,
                    unitCost: $item->unit_cost,
                    batchNumber: $this->normalizeOptionalText($line['batch_number'] ?? null),
                    expiryDate: $this->normalizeOptionalText($line['expiry_date'] ?? null),
                    notes: 'Purchase receipt '.$receipt->receipt_number.' for '.$locked->purchase_order_number,
                    movementDate: $receipt->receipt_date,
                    reference: $receipt,
                );

                $lineTotal = round($quantity * (float) $item->unit_cost, 2);

                PurchaseReceiptItem::query()->create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'stock_movement_id' => $movement->id,
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $lineTotal,
                    'batch_number' => $this->normalizeOptionalText($line['batch_number'] ?? null),
                    'expiry_date' => $this->normalizeOptionalText($line['expiry_date'] ?? null),
                ]);

                $item->received_quantity = round(
                    (float) $item->received_quantity + $quantity,
                    3,
                );
                $item->save();

                $receiptTotal += $lineTotal;
            }

            $receipt->forceFill([
                'total_amount' => round($receiptTotal, 2),
            ])->save();

            $hasRemaining = PurchaseOrderItem::query()
                ->where('purchase_order_id', $locked->id)
                ->whereColumn('received_quantity', '<', 'ordered_quantity')
                ->exists();

            $locked->forceFill([
                'status' => $hasRemaining
                    ? PurchaseOrder::STATUS_PARTIALLY_RECEIVED
                    : PurchaseOrder::STATUS_RECEIVED,
            ])->save();

            return $receipt->fresh([
                'items.product',
                'purchaseOrder.supplier',
                'warehouse',
            ]);
        });
    }

    public function cancel(PurchaseOrder $order, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $reason): PurchaseOrder {
            $locked = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($locked->receipts()->exists()) {
                throw new RuntimeException(
                    'لا يمكن إلغاء أمر شراء بعد تسجيل استلام فعلي. المخزون المستلم يبقى محفوظًا ويحتاج معالجة مستقلة.',
                );
            }

            if (! in_array($locked->status, [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_APPROVED,
            ], true)) {
                throw new RuntimeException('لا يمكن إلغاء أمر الشراء في حالته الحالية.');
            }

            if (mb_strlen(trim($reason)) < 5) {
                throw new RuntimeException('سبب الإلغاء يجب ألا يقل عن 5 محارف.');
            }

            $locked->forceFill([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
            ])->save();

            return $locked->refresh();
        });
    }

    public function recalculate(PurchaseOrder $order): PurchaseOrder
    {
        $total = round(
            (float) $order->items()->sum('line_total'),
            2,
        );

        $order->forceFill([
            'subtotal' => $total,
            'total_amount' => $total,
        ])->save();

        return $order->refresh();
    }

    private function ensureSupplierActive(?Supplier $supplier): void
    {
        if (! $supplier instanceof Supplier || $supplier->status !== 'active') {
            throw new RuntimeException('المورد غير فعال ولا يمكن استخدامه في عملية شراء جديدة.');
        }
    }

    private function ensureWarehouseAllowed(?Warehouse $warehouse): void
    {
        if (! $warehouse instanceof Warehouse || $warehouse->status !== 'active') {
            throw new RuntimeException('المستودع المحدد غير فعال.');
        }

        if (! in_array($warehouse->type, ['main', 'branch'], true)) {
            throw new RuntimeException(
                'أوامر الشراء تستلم في مستودع رئيسي أو فرعي فقط. مخزون السيارة يُغذّى عبر حمولة السيارة.',
            );
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $scope = app(AccessScopeService::class)->for($user);

        if (! $scope->unrestricted
            && ! in_array((int) $warehouse->getKey(), $scope->warehouseIds, true)) {
            throw new RuntimeException('المستودع المحدد خارج نطاق وصول المستخدم الحالي.');
        }
    }

    /** @param Collection<int, PurchaseOrderItem> $items */
    private function validateOrderItems(Collection $items): void
    {
        foreach ($items as $item) {
            if (! $item->product instanceof Product || $item->product->status !== 'active') {
                throw new RuntimeException('يتضمن أمر الشراء منتجًا غير فعال.');
            }

            if ((float) $item->ordered_quantity <= 0) {
                throw new RuntimeException('كمية أمر الشراء يجب أن تكون أكبر من الصفر.');
            }

            if ((float) $item->unit_cost < 0) {
                throw new RuntimeException('تكلفة الشراء لا يمكن أن تكون سالبة.');
            }
        }
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
