<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Distribution\DailyClosingGuard;
use App\Services\Support\DocumentNumberService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
        float|string|null $unitCost = null,
        string $movementType = 'opening_balance',
        ?string $notes = null,
        ?object $reference = null,
        CarbonInterface|string|null $movementDate = null,
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
            $reference,
            $movementDate,
        ): StockMovement {
            $date = $this->normalizeMovementDate($movementDate);
            app(DailyClosingGuard::class)->ensureOpen($date, $warehouse->id);

            $appliedUnitCost = $this->normalizeInboundUnitCost(
                $product,
                $unitCost,
            );

            $this->increaseBalance(
                warehouse: $warehouse,
                product: $product,
                quantity: $quantity,
                batchNumber: $batchNumber,
                expiryDate: $expiryDate,
                unitCost: $appliedUnitCost,
            );

            return $this->createMovement([
                'movement_type' => $movementType,
                'movement_date' => $date,
                'to_warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'quantity' => $quantity,
                'unit_cost' => $appliedUnitCost,
                'total_cost' => (float) $quantity * $appliedUnitCost,
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
        string $movementType = 'manual_out',
        ?string $notes = null,
        ?object $reference = null,
        CarbonInterface|string|null $movementDate = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $warehouse,
            $product,
            $quantity,
            $batchNumber,
            $expiryDate,
            $movementType,
            $notes,
            $reference,
            $movementDate,
        ): StockMovement {
            $date = $this->normalizeMovementDate($movementDate);
            app(DailyClosingGuard::class)->ensureOpen($date, $warehouse->id);

            $appliedUnitCost = $this->decreaseBalance(
                warehouse: $warehouse,
                product: $product,
                quantity: $quantity,
                batchNumber: $batchNumber,
                expiryDate: $expiryDate,
            );

            return $this->createMovement([
                'movement_type' => $movementType,
                'movement_date' => $date,
                'from_warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'quantity' => $quantity,
                'unit_cost' => $appliedUnitCost,
                'total_cost' => (float) $quantity * $appliedUnitCost,
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
        string $movementType = 'warehouse_transfer',
        ?string $notes = null,
        ?object $reference = null,
        CarbonInterface|string|null $movementDate = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $fromWarehouse,
            $toWarehouse,
            $product,
            $quantity,
            $batchNumber,
            $expiryDate,
            $movementType,
            $notes,
            $reference,
            $movementDate,
        ): StockMovement {
            if ($fromWarehouse->is($toWarehouse)) {
                throw new RuntimeException('لا يمكن تنفيذ تحويل مخزون داخل المستودع نفسه.');
            }

            $date = $this->normalizeMovementDate($movementDate);
            $guard = app(DailyClosingGuard::class);
            $guard->ensureOpen($date, $fromWarehouse->id);
            $guard->ensureOpen($date, $toWarehouse->id);

            $appliedUnitCost = $this->decreaseBalance(
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
                unitCost: $appliedUnitCost,
            );

            return $this->createMovement([
                'movement_type' => $movementType,
                'movement_date' => $date,
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'quantity' => $quantity,
                'unit_cost' => $appliedUnitCost,
                'total_cost' => (float) $quantity * $appliedUnitCost,
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
        float $unitCost,
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
                'average_unit_cost' => 0,
            ]);
        }

        $existingQuantity = (float) $balance->quantity;
        $existingUnitCost = $this->resolveBalanceUnitCost(
            $balance,
            $product,
        );
        $incomingQuantity = (float) $quantity;
        $newQuantity = $existingQuantity + $incomingQuantity;

        $newAverageUnitCost = $newQuantity > 0
            ? (
                ($existingQuantity * $existingUnitCost)
                + ($incomingQuantity * $unitCost)
            ) / $newQuantity
            : 0;

        $balance->quantity = $newQuantity;
        $balance->average_unit_cost = round($newAverageUnitCost, 6);
        $balance->save();

        return $balance;
    }

    private function decreaseBalance(
        Warehouse $warehouse,
        Product $product,
        float|string $quantity,
        ?string $batchNumber,
        ?string $expiryDate,
    ): float {
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

        $appliedUnitCost = $this->resolveBalanceUnitCost(
            $balance,
            $product,
        );

        $balance->quantity = (float) $balance->quantity - (float) $quantity;
        $balance->average_unit_cost = round($appliedUnitCost, 6);
        $balance->save();

        return $appliedUnitCost;
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

    private function normalizeInboundUnitCost(
        Product $product,
        float|string|null $unitCost,
    ): float {
        $cost = $unitCost === null
            ? (float) $product->purchase_price
            : (float) $unitCost;

        if ($cost < 0) {
            throw new RuntimeException('تكلفة الوحدة لا يمكن أن تكون سالبة.');
        }

        return round($cost, 6);
    }

    private function resolveBalanceUnitCost(
        StockBalance $balance,
        Product $product,
    ): float {
        $cost = (float) $balance->average_unit_cost;

        if ($cost <= 0 && (float) $balance->quantity > 0) {
            $cost = (float) $product->purchase_price;
            $balance->average_unit_cost = round($cost, 6);
        }

        return round(max($cost, 0), 6);
    }

    private function createMovement(array $data): StockMovement
    {
        return StockMovement::query()->create($data + [
            'movement_number' => $this->generateMovementNumber(),
            'created_by' => Auth::id(),
        ]);
    }

    private function normalizeMovementDate(
        CarbonInterface|string|null $movementDate,
    ): string {
        return $movementDate instanceof CarbonInterface
            ? $movementDate->toDateString()
            : Carbon::parse($movementDate ?: now())->toDateString();
    }

    private function generateMovementNumber(): string
    {
        return app(DocumentNumberService::class)->next('stock_movement', 'STM');
    }
}
