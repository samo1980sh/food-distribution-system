<?php

namespace App\Services\Distribution;

use App\Models\VehicleLoad;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VehicleLoadService
{
    public function approve(VehicleLoad $vehicleLoad): VehicleLoad
    {
        return DB::transaction(function () use ($vehicleLoad): VehicleLoad {
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

            $inventory = app(InventoryMovementService::class);

            foreach ($vehicleLoad->items as $item) {
                $inventory->transfer(
                    fromWarehouse: $vehicleLoad->fromWarehouse,
                    toWarehouse: $vehicleLoad->toWarehouse,
                    product: $item->product,
                    quantity: $item->quantity,
                    batchNumber: $item->batch_number,
                    expiryDate: $item->expiry_date?->toDateString(),
                    unitCost: $item->unit_cost,
                    movementType: 'vehicle_load_transfer',
                    notes: 'تحميل سيارة - أمر رقم '.$vehicleLoad->load_number,
                    reference: $vehicleLoad,
                );
            }

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
                    unitCost: $item->unit_cost,
                    movementType: 'vehicle_load_cancellation',
                    notes: 'إلغاء تحميل سيارة - أمر رقم '.$vehicleLoad->load_number,
                    reference: $vehicleLoad,
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
