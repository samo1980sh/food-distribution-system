<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class WarehouseReplenishmentService
{
    /** @var list<string> */
    private const FIXED_WAREHOUSE_TYPES = ['main', 'branch'];

    public function receive(
        Warehouse $warehouse,
        Product $product,
        float|string $quantity,
        float|string|null $unitCost,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        ?string $notes = null,
        CarbonInterface|string|null $movementDate = null,
        ?object $reference = null,
    ): StockMovement {
        $this->ensureFixedActiveWarehouse($warehouse, 'مستودع التوريد');
        $this->ensureWarehouseAllowed($warehouse);
        $this->ensureActiveProduct($product);
        $this->ensureExpiryPolicy($product, $expiryDate);
        $this->ensureNotes($notes);

        return app(InventoryMovementService::class)->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: $quantity,
            batchNumber: $this->normalizeOptionalText($batchNumber),
            expiryDate: $expiryDate,
            unitCost: $unitCost,
            movementType: 'stock_receipt',
            notes: trim((string) $notes),
            reference: $reference,
            movementDate: $movementDate,
        );
    }

    public function transfer(
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        Product $product,
        float|string $quantity,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        ?string $notes = null,
        CarbonInterface|string|null $movementDate = null,
    ): StockMovement {
        $this->ensureFixedActiveWarehouse($fromWarehouse, 'المستودع المصدر');
        $this->ensureFixedActiveWarehouse($toWarehouse, 'المستودع الهدف');
        $this->ensureWarehouseAllowed($fromWarehouse);
        $this->ensureWarehouseAllowed($toWarehouse);
        $this->ensureActiveProduct($product);
        $this->ensureExpiryPolicy($product, $expiryDate);
        $this->ensureNotes($notes);

        return app(InventoryMovementService::class)->transfer(
            fromWarehouse: $fromWarehouse,
            toWarehouse: $toWarehouse,
            product: $product,
            quantity: $quantity,
            batchNumber: $this->normalizeOptionalText($batchNumber),
            expiryDate: $expiryDate,
            movementType: 'warehouse_transfer',
            notes: trim((string) $notes),
            movementDate: $movementDate,
        );
    }

    private function ensureFixedActiveWarehouse(Warehouse $warehouse, string $label): void
    {
        if ($warehouse->status !== 'active') {
            throw new RuntimeException($label.' غير فعال ولا يمكن استخدامه في حركة مخزون جديدة.');
        }

        if (! in_array($warehouse->type, self::FIXED_WAREHOUSE_TYPES, true)) {
            throw new RuntimeException('مخزون السيارة يُدار عبر حمولة السيارة المعتمدة، وليس عبر التوريد أو التحويل الإداري.');
        }
    }

    private function ensureWarehouseAllowed(Warehouse $warehouse): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $scope = app(AccessScopeService::class)->for($user);

        if (! $scope->unrestricted && ! in_array((int) $warehouse->getKey(), $scope->warehouseIds, true)) {
            throw new RuntimeException('المستودع المحدد خارج نطاق وصول المستخدم الحالي.');
        }
    }

    private function ensureActiveProduct(Product $product): void
    {
        if ($product->status !== 'active') {
            throw new RuntimeException('المنتج المحدد غير فعال ولا يمكن استخدامه في حركة مخزون جديدة.');
        }
    }

    private function ensureExpiryPolicy(Product $product, ?string $expiryDate): void
    {
        $hasExpiryDate = filled($expiryDate);

        if ((bool) $product->has_expiry && ! $hasExpiryDate) {
            throw new RuntimeException('المنتج المحدد يتطلب تاريخ صلاحية.');
        }

        if (! (bool) $product->has_expiry && $hasExpiryDate) {
            throw new RuntimeException('المنتج المحدد لا يتطلب تتبع تاريخ صلاحية؛ اترك تاريخ الصلاحية فارغًا.');
        }
    }

    private function ensureNotes(?string $notes): void
    {
        if (mb_strlen(trim((string) $notes)) < 10) {
            throw new RuntimeException('مرجع أو سبب حركة المخزون يجب ألا يقل عن 10 محارف.');
        }
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
