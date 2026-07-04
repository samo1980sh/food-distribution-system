<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryMovementService
{
    public function addStock(
        Warehouse $warehouse,
        Product $product,
        float|string $quantity,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        float|string $unitCost = 0,
        string $movementType = 'opening_balance',
        ?string $notes = null,
        ?object $reference = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $warehouse,
            $product,
            $quantity,
            $batchNumber,
            $expiryDate,
            $unitCost,
            $movementType,
            $notes,
            $reference
        ): StockMovement {
            $this->increaseBalance(
                warehouse: $warehouse,
                product: $product,
                quantity: $quantity,
                batchNumber: $batchNumber,
                expiryDate: $expiryDate,
            );

            return $this->createMovement([
                'movement_type' => $movementType,
                'to_warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => (float) $quantity * (float) $unitCost,
                'notes' => $notes,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->id,
            ]);
        });
    }

    public function removeStock(
        Warehouse $warehouse,
        Product $product,
        float|string $quantity,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        float|string $unitCost = 0,
        string $movementType = 'manual_out',
        ?string $notes = null,
        ?object $reference = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $warehouse,
            $product,
            $quantity,
            $batchNumber,
            $expiryDate,
            $unitCost,
            $movementType,
            $notes,
            $reference
        ): StockMovement {
            $this->decreaseBalance(
                warehouse: $warehouse,
                product: $product,
                quantity: $quantity,
                batchNumber: $batchNumber,
                expiryDate: $expiryDate,
            );

            return $this->createMovement([
                'movement_type' => $movementType,
                'from_warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => (float) $quantity * (float) $unitCost,
                'notes' => $notes,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->id,
            ]);
        });
    }

    public function transfer(
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        Product $product,
        float|string $quantity,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        float|string $unitCost = 0,
        string $movementType = 'warehouse_transfer',
        ?string $notes = null,
        ?object $reference = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $fromWarehouse,
            $toWarehouse,
            $product,
            $quantity,
            $batchNumber,
            $expiryDate,
            $unitCost,
            $movementType,
            $notes,
            $reference
        ): StockMovement {
            $this->decreaseBalance(
                warehouse: $fromWarehouse,
                product: $product,
                quantity: $quantity,
                batchNumber: $batchNumber,
                expiryDate: $expiryDate,
            );

            $this->increaseBalance(
                warehouse: $toWarehouse,
                product: $product,
                quantity: $quantity,
                batchNumber: $batchNumber,
                expiryDate: $expiryDate,
            );

            return $this->createMovement([
                'movement_type' => $movementType,
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => (float) $quantity * (float) $unitCost,
                'notes' => $notes,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->id,
            ]);
        });
    }

    private function increaseBalance(
        Warehouse $warehouse,
        Product $product,
        float|string $quantity,
        ?string $batchNumber,
        ?string $expiryDate,
    ): StockBalance {
        if ((float) $quantity <= 0) {
            throw new RuntimeException('كمية حركة المخزون يجب أن تكون أكبر من الصفر.');
        }

        $batchKey = $this->normalizeBatchKey($batchNumber);
        $expiryKey = $this->normalizeExpiryKey($expiryDate);

        $balance = $this->balanceQuery($warehouse->id, $product->id, $batchNumber, $expiryDate)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = StockBalance::query()->create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'batch_key' => $batchKey,
                'expiry_date' => $expiryDate,
                'expiry_key' => $expiryKey,
                'quantity' => 0,
            ]);
        }

        $balance->quantity = (float) $balance->quantity + (float) $quantity;
        $balance->save();

        return $balance;
    }

    private function decreaseBalance(
        Warehouse $warehouse,
        Product $product,
        float|string $quantity,
        ?string $batchNumber,
        ?string $expiryDate,
    ): StockBalance {
        if ((float) $quantity <= 0) {
            throw new RuntimeException('كمية حركة المخزون يجب أن تكون أكبر من الصفر.');
        }

        $balance = $this->balanceQuery($warehouse->id, $product->id, $batchNumber, $expiryDate)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            throw new RuntimeException('لا يوجد رصيد لهذا المنتج في المستودع المحدد.');
        }

        if ((float) $balance->quantity < (float) $quantity) {
            throw new RuntimeException('الرصيد المتوفر لا يكفي لتنفيذ حركة المخزون.');
        }

        $balance->quantity = (float) $balance->quantity - (float) $quantity;
        $balance->save();

        return $balance;
    }

    private function balanceQuery(
        int $warehouseId,
        int $productId,
        ?string $batchNumber,
        ?string $expiryDate,
    ): Builder {
        return StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('batch_key', $this->normalizeBatchKey($batchNumber))
            ->where('expiry_key', $this->normalizeExpiryKey($expiryDate));
    }

    private function normalizeBatchKey(?string $batchNumber): string
    {
        return trim((string) $batchNumber);
    }

    private function normalizeExpiryKey(?string $expiryDate): string
    {
        return $expiryDate ? date('Y-m-d', strtotime($expiryDate)) : '';
    }

    private function createMovement(array $data): StockMovement
    {
        return StockMovement::query()->create($data + [
            'movement_number' => $this->generateMovementNumber(),
            'created_by' => Auth::id(),
        ]);
    }

    private function generateMovementNumber(): string
    {
        return app(DocumentNumberService::class)->next('stock_movement', 'STM');
    }
}
