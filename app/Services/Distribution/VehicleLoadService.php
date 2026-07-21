<?php

namespace App\Services\Distribution;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\VehicleLoad;
use App\Models\VehicleLoadItem;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VehicleLoadService
{
    private const QUANTITY_SCALE = 1000;

    public function approve(VehicleLoad $vehicleLoad): VehicleLoad
    {
        return DB::transaction(function () use ($vehicleLoad): VehicleLoad {
            $vehicleLoad = VehicleLoad::query()
                ->lockForUpdate()
                ->findOrFail($vehicleLoad->getKey());

            $vehicleLoad->loadMissing([
                'items.product',
                'fromWarehouse',
                'toWarehouse',
                'vehicle',
            ]);

            if (! $vehicleLoad->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد أمر تحميل ليس بحالة مسودة.');
            }

            $this->validateWarehouses($vehicleLoad);
            app(DailyClosingGuard::class)->ensureOpen($vehicleLoad->load_date, $vehicleLoad->to_warehouse_id);

            if ($vehicleLoad->items->isEmpty()) {
                throw new RuntimeException('لا يمكن اعتماد أمر تحميل بدون مواد.');
            }

            $allocations = $this->buildFefoAllocations($vehicleLoad);

            $vehicleLoad->items()->delete();

            $inventory = app(InventoryMovementService::class);

            foreach ($allocations as $allocation) {
                $movement = $inventory->transfer(
                    fromWarehouse: $vehicleLoad->fromWarehouse,
                    toWarehouse: $vehicleLoad->toWarehouse,
                    product: $allocation['product'],
                    quantity: $allocation['quantity'],
                    batchNumber: $allocation['batch_number'],
                    expiryDate: $allocation['expiry_date'],
                    movementType: 'vehicle_load_transfer',
                    notes: 'تحميل سيارة - أمر رقم '.$vehicleLoad->load_number,
                    reference: $vehicleLoad,
                    movementDate: $vehicleLoad->load_date,
                );

                VehicleLoadItem::withoutEvents(function () use ($vehicleLoad, $allocation, $movement): void {
                    VehicleLoadItem::query()->create([
                        'vehicle_load_id' => $vehicleLoad->id,
                        'product_id' => $allocation['product']->id,
                        'batch_number' => $allocation['batch_number'],
                        'expiry_date' => $allocation['expiry_date'],
                        'quantity' => $allocation['quantity'],
                        'received_quantity' => null,
                        'handover_note' => null,
                        'unit_cost' => $movement->unit_cost,
                        'total_cost' => $movement->total_cost,
                    ]);
                });
            }

            $vehicleLoad->unsetRelation('items');
            $this->recalculateTotals($vehicleLoad);

            $vehicleLoad->forceFill([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ])->save();

            return $vehicleLoad;
        });
    }

    public function cancel(VehicleLoad $vehicleLoad): VehicleLoad
    {
        return DB::transaction(function () use ($vehicleLoad): VehicleLoad {
            $vehicleLoad = VehicleLoad::query()
                ->lockForUpdate()
                ->findOrFail($vehicleLoad->getKey());

            $vehicleLoad->loadMissing([
                'items.product',
                'fromWarehouse',
                'toWarehouse',
            ]);

            if (! $vehicleLoad->isApproved()) {
                throw new RuntimeException('لا يمكن إلغاء أمر تحميل غير معتمد.');
            }

            app(DailyClosingGuard::class)->ensureOpen($vehicleLoad->load_date, $vehicleLoad->to_warehouse_id);

            $inventory = app(InventoryMovementService::class);

            foreach ($vehicleLoad->items as $item) {
                $inventory->transfer(
                    fromWarehouse: $vehicleLoad->toWarehouse,
                    toWarehouse: $vehicleLoad->fromWarehouse,
                    product: $item->product,
                    quantity: $item->quantity,
                    batchNumber: $item->batch_number,
                    expiryDate: $item->expiry_date?->toDateString(),
                    movementType: 'vehicle_load_cancellation',
                    notes: 'إلغاء تحميل سيارة - أمر رقم '.$vehicleLoad->load_number,
                    reference: $vehicleLoad,
                    movementDate: $vehicleLoad->load_date,
                );
            }

            $vehicleLoad->forceFill([
                'status' => 'cancelled',
            ])->save();

            return $vehicleLoad;
        });
    }

    public function recalculateTotals(VehicleLoad $vehicleLoad): void
    {
        $totals = $vehicleLoad->items()
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(total_cost), 0) as total_cost')
            ->first();

        $vehicleLoad->forceFill([
            'total_quantity' => $totals?->total_quantity ?? 0,
            'total_cost' => $totals?->total_cost ?? 0,
        ])->save();
    }

    public function generateLoadNumber(): string
    {
        return app(DocumentNumberService::class)->next('vehicle_load', 'VLD');
    }

    /**
     * @return list<array{
     *     product: Product,
     *     quantity: string,
     *     batch_number: ?string,
     *     expiry_date: ?string
     * }>
     */
    private function buildFefoAllocations(VehicleLoad $vehicleLoad): array
    {
        $requests = [];

        foreach ($vehicleLoad->items as $item) {
            $requestedUnits = $this->quantityToUnits($item->quantity);

            if ($requestedUnits <= 0) {
                throw new RuntimeException('كمية كل مادة في أمر التحميل يجب أن تكون أكبر من الصفر.');
            }

            if (! $item->product) {
                throw new RuntimeException('تعذر العثور على أحد المنتجات المحددة في أمر التحميل.');
            }

            $productId = (int) $item->product_id;

            if (! isset($requests[$productId])) {
                $requests[$productId] = [
                    'product' => $item->product,
                    'quantity_units' => 0,
                ];
            }

            $requests[$productId]['quantity_units'] += $requestedUnits;
        }

        $allocations = [];
        $loadDate = $vehicleLoad->load_date?->toDateString() ?? now()->toDateString();

        foreach ($requests as $request) {
            /** @var Product $product */
            $product = $request['product'];
            $requestedUnits = (int) $request['quantity_units'];
            $remainingUnits = $requestedUnits;
            $availableUnits = 0;

            $balances = StockBalance::query()
                ->where('warehouse_id', $vehicleLoad->from_warehouse_id)
                ->where('product_id', $product->id)
                ->where('quantity', '>', 0)
                ->where(function ($query) use ($loadDate): void {
                    $query
                        ->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', $loadDate);
                })
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($balances as $balance) {
                $balanceUnits = $this->quantityToUnits($balance->quantity);
                $availableUnits += $balanceUnits;

                if ($remainingUnits <= 0 || $balanceUnits <= 0) {
                    continue;
                }

                $allocatedUnits = min($remainingUnits, $balanceUnits);

                $allocations[] = [
                    'product' => $product,
                    'quantity' => $this->unitsToQuantity($allocatedUnits),
                    'batch_number' => filled($balance->batch_number)
                        ? (string) $balance->batch_number
                        : null,
                    'expiry_date' => $balance->expiry_date?->toDateString(),
                ];

                $remainingUnits -= $allocatedUnits;
            }

            if ($remainingUnits > 0) {
                $label = $this->productLabel($product);
                $requested = $this->unitsToDisplayQuantity($requestedUnits);
                $available = $this->unitsToDisplayQuantity($availableUnits);

                throw new RuntimeException(
                    "الرصيد الصالح للمنتج «{$label}» لا يكفي. المطلوب {$requested} والمتاح {$available}.",
                );
            }
        }

        return $allocations;
    }

    private function quantityToUnits(float|string $quantity): int
    {
        return (int) round((float) $quantity * self::QUANTITY_SCALE);
    }

    private function unitsToQuantity(int $units): string
    {
        return number_format($units / self::QUANTITY_SCALE, 3, '.', '');
    }

    private function unitsToDisplayQuantity(int $units): string
    {
        return rtrim(rtrim($this->unitsToQuantity($units), '0'), '.');
    }

    private function productLabel(Product $product): string
    {
        return (string) ($product->name_ar ?: $product->sku ?: $product->id);
    }

    private function validateWarehouses(VehicleLoad $vehicleLoad): void
    {
        if ($vehicleLoad->from_warehouse_id === $vehicleLoad->to_warehouse_id) {
            throw new RuntimeException('لا يمكن تحميل السيارة من المستودع نفسه وإليه.');
        }

        if ($vehicleLoad->toWarehouse?->type !== 'vehicle') {
            throw new RuntimeException('مستودع الوجهة يجب أن يكون مستودع سيارة.');
        }

        if ((int) $vehicleLoad->toWarehouse?->vehicle_id !== (int) $vehicleLoad->vehicle_id) {
            throw new RuntimeException('مستودع الوجهة لا يتبع السيارة المحددة.');
        }
    }
}
