<?php

namespace App\Services\Distribution;

use App\Models\VehicleLoad;
use App\Services\Inventory\InventoryMovementService;
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
            ]);

            if (! $vehicleLoad->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد أمر تحميل غير موجود بحالة مسودة.');
            }

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
                    notes: 'تحميل سيارة - أمر رقم ' . $vehicleLoad->load_number,
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
        $date = now()->format('Ymd');

        $count = VehicleLoad::query()
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return 'VLD-' . $date . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}