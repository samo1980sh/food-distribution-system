<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdministrativeStockMovementService
{
    /** @var list<string> */
    public const ADMINISTRATIVE_TYPES = [
        'opening_balance',
        'manual_out',
        'warehouse_transfer',
    ];

    public static function isAdministrativeType(?string $movementType): bool
    {
        return in_array($movementType, self::ADMINISTRATIVE_TYPES, true);
    }

    public function canActOn(StockMovement $movement): bool
    {
        return self::isAdministrativeType($movement->movement_type)
            && ! $this->hasBeenReversed($movement);
    }

    public function hasBeenReversed(StockMovement $movement): bool
    {
        return StockMovement::query()
            ->where('movement_type', 'administrative_reversal')
            ->where('reference_type', StockMovement::class)
            ->where('reference_id', $movement->getKey())
            ->exists();
    }

    public function reverse(
        StockMovement $movement,
        string $reason,
        CarbonInterface|string|null $movementDate = null,
    ): StockMovement {
        $this->ensureCanActOn($movement);
        $this->ensureReason($reason);
        $this->ensureCurrentUserCanReachOriginalWarehouses($movement);

        return DB::transaction(function () use ($movement, $reason, $movementDate): StockMovement {
            $inventory = app(InventoryMovementService::class);
            $product = $movement->product()->withoutGlobalScopes()->firstOrFail();
            $notes = 'عكس إداري للحركة '.$movement->movement_number.': '.trim($reason);

            return match ($movement->movement_type) {
                'opening_balance' => $inventory->removeStock(
                    warehouse: $this->requiredWarehouse($movement->to_warehouse_id),
                    product: $product,
                    quantity: $movement->quantity,
                    batchNumber: $movement->batch_number,
                    expiryDate: $movement->expiry_date?->toDateString(),
                    movementType: 'administrative_reversal',
                    notes: $notes,
                    reference: $movement,
                    movementDate: $movementDate,
                ),

                'manual_out' => $inventory->addStock(
                    warehouse: $this->requiredWarehouse($movement->from_warehouse_id),
                    product: $product,
                    quantity: $movement->quantity,
                    batchNumber: $movement->batch_number,
                    expiryDate: $movement->expiry_date?->toDateString(),
                    unitCost: $movement->unit_cost,
                    movementType: 'administrative_reversal',
                    notes: $notes,
                    reference: $movement,
                    movementDate: $movementDate,
                ),

                'warehouse_transfer' => $inventory->transfer(
                    fromWarehouse: $this->requiredWarehouse($movement->to_warehouse_id),
                    toWarehouse: $this->requiredWarehouse($movement->from_warehouse_id),
                    product: $product,
                    quantity: $movement->quantity,
                    batchNumber: $movement->batch_number,
                    expiryDate: $movement->expiry_date?->toDateString(),
                    movementType: 'administrative_reversal',
                    notes: $notes,
                    reference: $movement,
                    movementDate: $movementDate,
                ),

                default => throw new RuntimeException('هذه الحركة ليست حركة مخزون إدارية قابلة للعكس.'),
            };
        });
    }

    /**
     * @param array{
     *     movement_date?: string|null,
     *     product_id: int|string,
     *     from_warehouse_id?: int|string|null,
     *     to_warehouse_id?: int|string|null,
     *     batch_number?: string|null,
     *     expiry_date?: string|null,
     *     quantity: int|float|string,
     *     unit_cost?: int|float|string|null
     * } $corrected
     * @return array{reversal: StockMovement, corrected: StockMovement}
     */
    public function correct(
        StockMovement $movement,
        array $corrected,
        string $reason,
    ): array {
        $this->ensureCanActOn($movement);
        $this->ensureReason($reason);
        $this->ensureCurrentUserCanReachOriginalWarehouses($movement);

        $product = Product::withoutGlobalScopes()
            ->whereKey($corrected['product_id'])
            ->where('status', 'active')
            ->firstOrFail();

        $fromWarehouse = filled($corrected['from_warehouse_id'] ?? null)
            ? $this->requiredWarehouse((int) $corrected['from_warehouse_id'])
            : null;

        $toWarehouse = filled($corrected['to_warehouse_id'] ?? null)
            ? $this->requiredWarehouse((int) $corrected['to_warehouse_id'])
            : null;

        $this->ensureWarehouseAllowed($fromWarehouse);
        $this->ensureWarehouseAllowed($toWarehouse);
        $this->ensureCorrectedPayloadMatchesType($movement->movement_type, $fromWarehouse, $toWarehouse);
        $this->ensureExpiryPolicy($product, $corrected['expiry_date'] ?? null);

        $quantity = (float) $corrected['quantity'];
        if ($quantity <= 0) {
            throw new RuntimeException('الكمية المصححة يجب أن تكون أكبر من الصفر.');
        }

        $movementDate = filled($corrected['movement_date'] ?? null)
            ? (string) $corrected['movement_date']
            : now()->toDateString();

        return DB::transaction(function () use (
            $movement,
            $corrected,
            $reason,
            $product,
            $fromWarehouse,
            $toWarehouse,
            $quantity,
            $movementDate,
        ): array {
            $reversal = $this->reverse(
                movement: $movement,
                reason: 'بدء تصحيح موثق - '.trim($reason),
                movementDate: $movementDate,
            );

            $inventory = app(InventoryMovementService::class);
            $notes = 'تصحيح للحركة '.$movement->movement_number.': '.trim($reason);
            $batchNumber = filled($corrected['batch_number'] ?? null)
                ? trim((string) $corrected['batch_number'])
                : null;
            $expiryDate = filled($corrected['expiry_date'] ?? null)
                ? (string) $corrected['expiry_date']
                : null;

            $newMovement = match ($movement->movement_type) {
                'opening_balance' => $inventory->addStock(
                    warehouse: $toWarehouse ?? throw new RuntimeException('المستودع الهدف مطلوب للتصحيح.'),
                    product: $product,
                    quantity: $quantity,
                    batchNumber: $batchNumber,
                    expiryDate: $expiryDate,
                    unitCost: $corrected['unit_cost'] ?? 0,
                    movementType: 'opening_balance',
                    notes: $notes,
                    reference: $movement,
                    movementDate: $movementDate,
                ),

                'manual_out' => $inventory->removeStock(
                    warehouse: $fromWarehouse ?? throw new RuntimeException('المستودع المصدر مطلوب للتصحيح.'),
                    product: $product,
                    quantity: $quantity,
                    batchNumber: $batchNumber,
                    expiryDate: $expiryDate,
                    movementType: 'manual_out',
                    notes: $notes,
                    reference: $movement,
                    movementDate: $movementDate,
                ),

                'warehouse_transfer' => $inventory->transfer(
                    fromWarehouse: $fromWarehouse ?? throw new RuntimeException('المستودع المصدر مطلوب للتصحيح.'),
                    toWarehouse: $toWarehouse ?? throw new RuntimeException('المستودع الهدف مطلوب للتصحيح.'),
                    product: $product,
                    quantity: $quantity,
                    batchNumber: $batchNumber,
                    expiryDate: $expiryDate,
                    movementType: 'warehouse_transfer',
                    notes: $notes,
                    reference: $movement,
                    movementDate: $movementDate,
                ),

                default => throw new RuntimeException('هذه الحركة ليست حركة مخزون إدارية قابلة للتصحيح.'),
            };

            return [
                'reversal' => $reversal,
                'corrected' => $newMovement,
            ];
        });
    }

    private function ensureCanActOn(StockMovement $movement): void
    {
        if (! self::isAdministrativeType($movement->movement_type)) {
            throw new RuntimeException('الحركات التشغيلية لا تُعدّل من سجل المخزون. ألغِ العملية من مصدرها التشغيلي.');
        }

        if ($this->hasBeenReversed($movement)) {
            throw new RuntimeException('تم عكس هذه الحركة مسبقًا ولا يمكن عكسها أو تصحيحها مرة أخرى.');
        }
    }

    private function ensureReason(string $reason): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new RuntimeException('سبب العكس أو التصحيح يجب ألا يقل عن 10 محارف.');
        }
    }

    private function ensureCurrentUserCanReachOriginalWarehouses(StockMovement $movement): void
    {
        if ($movement->from_warehouse_id !== null) {
            $this->ensureWarehouseAllowed($this->requiredWarehouse((int) $movement->from_warehouse_id));
        }

        if ($movement->to_warehouse_id !== null) {
            $this->ensureWarehouseAllowed($this->requiredWarehouse((int) $movement->to_warehouse_id));
        }
    }

    private function ensureWarehouseAllowed(?Warehouse $warehouse): void
    {
        if (! $warehouse instanceof Warehouse) {
            return;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('تعذر تحديد المستخدم الحالي لتنفيذ حركة المخزون الإدارية.');
        }

        $scope = app(AccessScopeService::class)->for($user);

        if (! $scope->unrestricted && ! in_array((int) $warehouse->getKey(), $scope->warehouseIds, true)) {
            throw new RuntimeException('المستودع المحدد خارج نطاق وصول المستخدم الحالي.');
        }
    }

    private function ensureCorrectedPayloadMatchesType(
        string $movementType,
        ?Warehouse $fromWarehouse,
        ?Warehouse $toWarehouse,
    ): void {
        match ($movementType) {
            'opening_balance' => $toWarehouse instanceof Warehouse
                ? true
                : throw new RuntimeException('المستودع الهدف مطلوب للرصيد الافتتاحي المصحح.'),
            'manual_out' => $fromWarehouse instanceof Warehouse
                ? true
                : throw new RuntimeException('المستودع المصدر مطلوب للإخراج المصحح.'),
            'warehouse_transfer' => ($fromWarehouse instanceof Warehouse && $toWarehouse instanceof Warehouse)
                ? true
                : throw new RuntimeException('المستودع المصدر والهدف مطلوبان للتحويل المصحح.'),
            default => throw new RuntimeException('نوع الحركة غير قابل للتصحيح من سجل المخزون.'),
        };

        if (
            $movementType === 'warehouse_transfer'
            && $fromWarehouse?->is($toWarehouse)
        ) {
            throw new RuntimeException('لا يمكن أن يكون مستودع المصدر والهدف متطابقين.');
        }
    }

    private function ensureExpiryPolicy(Product $product, mixed $expiryDate): void
    {
        $hasExpiryDate = filled($expiryDate);

        if ((bool) $product->has_expiry && ! $hasExpiryDate) {
            throw new RuntimeException('المنتج المحدد يتطلب تاريخ صلاحية.');
        }

        if (! (bool) $product->has_expiry && $hasExpiryDate) {
            throw new RuntimeException('المنتج المحدد لا يتطلب تتبع تاريخ صلاحية؛ اترك تاريخ الصلاحية فارغًا.');
        }
    }

    private function requiredWarehouse(int|string|null $warehouseId): Warehouse
    {
        if (! filled($warehouseId)) {
            throw new RuntimeException('تعذر تحديد المستودع المرتبط بالحركة.');
        }

        return Warehouse::withoutGlobalScopes()->findOrFail((int) $warehouseId);
    }
}
