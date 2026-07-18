<?php

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryMovementService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ManageStockMovements extends ManageRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة حركة مخزون')
                ->visible(fn (): bool => StockMovementResource::canCreate())
                ->modalHeading('إضافة حركة مخزون')
                ->slideOver()
                ->using(function (array $data): StockMovement {
                    $service = app(InventoryMovementService::class);
                    $product = Product::query()->findOrFail($data['product_id']);

                    try {
                        $movementDate = (string) $data['movement_date'];

                        return match ($data['movement_type']) {
                            'opening_balance' => $service->addStock(
                                warehouse: Warehouse::query()->findOrFail($data['to_warehouse_id']),
                                product: $product,
                                quantity: $data['quantity'],
                                batchNumber: $data['batch_number'] ?? null,
                                expiryDate: $data['expiry_date'] ?? null,
                                unitCost: $data['unit_cost'] ?? 0,
                                movementType: 'opening_balance',
                                notes: $data['notes'] ?? null,
                                movementDate: $movementDate,
                            ),

                            'manual_out' => $service->removeStock(
                                warehouse: Warehouse::query()->findOrFail($data['from_warehouse_id']),
                                product: $product,
                                quantity: $data['quantity'],
                                batchNumber: $data['batch_number'] ?? null,
                                expiryDate: $data['expiry_date'] ?? null,
                                movementType: 'manual_out',
                                notes: $data['notes'] ?? null,
                                movementDate: $movementDate,
                            ),

                            'warehouse_transfer' => $service->transfer(
                                fromWarehouse: Warehouse::query()->findOrFail($data['from_warehouse_id']),
                                toWarehouse: Warehouse::query()->findOrFail($data['to_warehouse_id']),
                                product: $product,
                                quantity: $data['quantity'],
                                batchNumber: $data['batch_number'] ?? null,
                                expiryDate: $data['expiry_date'] ?? null,
                                movementType: 'warehouse_transfer',
                                notes: $data['notes'] ?? null,
                                movementDate: $movementDate,
                            ),

                            default => throw ValidationException::withMessages([
                                'movement_type' => 'نوع حركة المخزون غير صالح.',
                            ]),
                        };
                    } catch (RuntimeException $exception) {
                        throw ValidationException::withMessages([
                            'quantity' => $exception->getMessage(),
                        ]);
                    }
                }),
        ];
    }
}
