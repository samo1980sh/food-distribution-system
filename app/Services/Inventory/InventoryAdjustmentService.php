<?php

namespace App\Services\Inventory;

use App\Enums\PermissionName;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class InventoryAdjustmentService
{
    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    /** @var array<string, string> */
    public const REASON_CATEGORIES = [
        'inventory_variance' => 'فرق جرد',
        'damage' => 'تلف',
        'expired' => 'انتهاء صلاحية',
        'loss' => 'فقد / نقص',
        'prior_entry_correction' => 'تصحيح إدخال سابق',
        'discovered_surplus' => 'زيادة فعلية مكتشفة',
        'other_exception' => 'سبب استثنائي آخر',
    ];

    /** @var list<string> */
    private const IN_REASONS = [
        'inventory_variance',
        'prior_entry_correction',
        'discovered_surplus',
        'other_exception',
    ];

    /** @var list<string> */
    private const OUT_REASONS = [
        'inventory_variance',
        'damage',
        'expired',
        'loss',
        'prior_entry_correction',
        'other_exception',
    ];

    public function create(
        Warehouse $warehouse,
        Product $product,
        string $direction,
        float|string $quantity,
        string $reasonCategory,
        string $reason,
        float|string|null $unitCost = null,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        CarbonInterface|string|null $movementDate = null,
    ): StockMovement {
        $this->authorize($warehouse);
        $this->validate($warehouse, $product, $direction, $reasonCategory, $reason, $unitCost, $expiryDate);

        $notes = self::REASON_CATEGORIES[$reasonCategory].': '.trim($reason);
        $inventory = app(InventoryMovementService::class);

        if ($direction === self::DIRECTION_IN) {
            return $inventory->addStock(
                warehouse: $warehouse,
                product: $product,
                quantity: $quantity,
                batchNumber: $this->optionalText($batchNumber),
                expiryDate: $expiryDate,
                unitCost: $unitCost,
                movementType: 'inventory_adjustment_in',
                notes: $notes,
                movementDate: $movementDate,
            );
        }

        return $inventory->removeStock(
            warehouse: $warehouse,
            product: $product,
            quantity: $quantity,
            batchNumber: $this->optionalText($batchNumber),
            expiryDate: $expiryDate,
            movementType: 'inventory_adjustment_out',
            notes: $notes,
            movementDate: $movementDate,
        );
    }

    private function authorize(Warehouse $warehouse): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->can(PermissionName::INVENTORY_ADJUSTMENTS_CREATE->value)) {
            throw new RuntimeException('لا تملك صلاحية إنشاء تسوية مخزون.');
        }

        $scope = app(AccessScopeService::class)->for($user);

        if (! $scope->unrestricted && ! in_array((int) $warehouse->getKey(), $scope->warehouseIds, true)) {
            throw new RuntimeException('المستودع المحدد خارج نطاق وصول المستخدم الحالي.');
        }
    }

    private function validate(
        Warehouse $warehouse,
        Product $product,
        string $direction,
        string $reasonCategory,
        string $reason,
        float|string|null $unitCost,
        ?string $expiryDate,
    ): void {
        if ($warehouse->status !== 'active' || ! in_array($warehouse->type, ['main', 'branch'], true)) {
            throw new RuntimeException('التسويات مسموحة فقط للمستودعات الرئيسية والفرعية الفعالة.');
        }

        if ($product->status !== 'active') {
            throw new RuntimeException('المنتج المحدد غير فعال.');
        }

        $allowedReasons = $direction === self::DIRECTION_IN ? self::IN_REASONS : self::OUT_REASONS;
        if (! in_array($direction, [self::DIRECTION_IN, self::DIRECTION_OUT], true)
            || ! in_array($reasonCategory, $allowedReasons, true)) {
            throw new RuntimeException('سبب التسوية غير مناسب للاتجاه المحدد.');
        }

        if (mb_strlen(trim($reason)) < 10) {
            throw new RuntimeException('السبب التفصيلي يجب ألا يقل عن 10 محارف.');
        }

        if ((bool) $product->has_expiry !== filled($expiryDate)) {
            throw new RuntimeException((bool) $product->has_expiry
                ? 'المنتج المحدد يتطلب تاريخ صلاحية.'
                : 'المنتج المحدد لا يتطلب تتبع تاريخ صلاحية.');
        }

        if ($direction === self::DIRECTION_IN && ($unitCost === null || (float) $unitCost <= 0)) {
            throw new RuntimeException('تكلفة وحدة موجبة مطلوبة لتسوية زيادة المخزون.');
        }
    }

    private function optionalText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
