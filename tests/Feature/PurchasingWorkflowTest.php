<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\WarehouseReplenishmentService;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PurchasingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]));
    }

    public function test_purchase_order_approval_does_not_move_stock_and_partial_receipts_post_once(): void
    {
        $warehouse = $this->warehouse('WH-PO', 'main');
        $supplier = $this->supplier('SUP-PO');
        $product = $this->product('PRD-PO', false);

        app(WarehouseReplenishmentService::class)->receive(
            warehouse: $warehouse,
            product: $product,
            quantity: 10,
            unitCost: 10,
            notes: 'Opening supplier stock test',
        );

        $order = $this->order($supplier, $warehouse, $product, 10, 20);
        $service = app(PurchaseOrderService::class);

        $approved = $service->approve($order);

        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $approved->status);
        $this->assertEqualsWithDelta(10, (float) $this->balance($warehouse, $product)->quantity, 0.0001);

        $first = $service->receive($approved, [[
            'purchase_order_item_id' => $order->items()->firstOrFail()->id,
            'quantity' => 4,
        ]]);

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->fresh()->status);
        $this->assertEqualsWithDelta(14, (float) $this->balance($warehouse, $product)->quantity, 0.0001);
        $this->assertEqualsWithDelta(12.857142, (float) $this->balance($warehouse, $product)->average_unit_cost, 0.0001);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'stock_receipt',
            'reference_type' => PurchaseReceipt::class,
            'reference_id' => $first->id,
        ]);

        $second = $service->receive($order->fresh(), [[
            'purchase_order_item_id' => $order->items()->firstOrFail()->id,
            'quantity' => 6,
        ]]);

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->fresh()->status);
        $this->assertEqualsWithDelta(20, (float) $this->balance($warehouse, $product)->quantity, 0.0001);
        $this->assertEqualsWithDelta(15, (float) $this->balance($warehouse, $product)->average_unit_cost, 0.0001);
        $this->assertSame(2, PurchaseReceipt::query()->count());
        $this->assertSame(2, StockMovement::query()
            ->where('movement_type', 'stock_receipt')
            ->where('reference_type', PurchaseReceipt::class)
            ->count());
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_over_receipt_is_rejected_atomically(): void
    {
        $warehouse = $this->warehouse('WH-OVER', 'main');
        $supplier = $this->supplier('SUP-OVER');
        $product = $this->product('PRD-OVER', false);
        $order = app(PurchaseOrderService::class)->approve(
            $this->order($supplier, $warehouse, $product, 5, 11),
        );

        $itemId = $order->items()->firstOrFail()->id;

        try {
            app(PurchaseOrderService::class)->receive($order, [[
                'purchase_order_item_id' => $itemId,
                'quantity' => 6,
            ]]);

            $this->fail('Expected over-receipt rejection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('تتجاوز', $exception->getMessage());
        }

        $this->assertSame(0, PurchaseReceipt::query()->count());
        $this->assertSame(0, StockMovement::query()
            ->where('reference_type', PurchaseReceipt::class)
            ->count());
        $this->assertDatabaseMissing('stock_balances', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_purchase_order_cannot_target_vehicle_warehouse(): void
    {
        $warehouse = $this->vehicleWarehouse('WH-VEH');
        $supplier = $this->supplier('SUP-VEH');
        $product = $this->product('PRD-VEH', false);
        $order = $this->order($supplier, $warehouse, $product, 3, 12);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('حمولة السيارة');

        app(PurchaseOrderService::class)->approve($order);
    }

    public function test_inactive_supplier_cannot_be_approved(): void
    {
        $warehouse = $this->warehouse('WH-INACTIVE', 'main');
        $supplier = $this->supplier('SUP-INACTIVE');
        $supplier->update(['status' => 'inactive']);
        $product = $this->product('PRD-INACTIVE', false);
        $order = $this->order($supplier, $warehouse, $product, 3, 12);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('المورد غير فعال');

        app(PurchaseOrderService::class)->approve($order);
    }

    public function test_order_with_real_receipt_cannot_be_cancelled_or_reverse_received_stock(): void
    {
        $warehouse = $this->warehouse('WH-CANCEL', 'main');
        $supplier = $this->supplier('SUP-CANCEL');
        $product = $this->product('PRD-CANCEL', false);
        $service = app(PurchaseOrderService::class);

        $order = $service->approve(
            $this->order($supplier, $warehouse, $product, 5, 12),
        );

        $service->receive($order, [[
            'purchase_order_item_id' => $order->items()->firstOrFail()->id,
            'quantity' => 2,
        ]]);

        try {
            $service->cancel($order->fresh(), 'Supplier changed delivery terms');
            $this->fail('Expected cancellation rejection after receipt.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('بعد تسجيل استلام فعلي', $exception->getMessage());
        }

        $this->assertEqualsWithDelta(2, (float) $this->balance($warehouse, $product)->quantity, 0.0001);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->fresh()->status);
    }

    public function test_draft_or_unreceived_approved_order_can_be_cancelled_without_inventory_effect(): void
    {
        $warehouse = $this->warehouse('WH-CANCEL2', 'main');
        $supplier = $this->supplier('SUP-CANCEL2');
        $product = $this->product('PRD-CANCEL2', false);
        $service = app(PurchaseOrderService::class);

        $order = $service->approve(
            $this->order($supplier, $warehouse, $product, 5, 12),
        );

        $cancelled = $service->cancel($order, 'Supplier unavailable');

        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $cancelled->status);
        $this->assertDatabaseMissing('stock_balances', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_receipt_item_links_exact_stock_movement(): void
    {
        $warehouse = $this->warehouse('WH-LINK', 'main');
        $supplier = $this->supplier('SUP-LINK');
        $product = $this->product('PRD-LINK', false);
        $service = app(PurchaseOrderService::class);
        $order = $service->approve(
            $this->order($supplier, $warehouse, $product, 2, 9),
        );

        $receipt = $service->receive($order, [[
            'purchase_order_item_id' => $order->items()->firstOrFail()->id,
            'quantity' => 2,
        ]]);

        $item = $receipt->items()->firstOrFail();

        $this->assertNotNull($item->stock_movement_id);
        $this->assertSame($receipt->id, $item->stockMovement?->reference_id);
        $this->assertSame(PurchaseReceipt::class, $item->stockMovement?->reference_type);
        $this->assertSame('stock_receipt', $item->stockMovement?->movement_type);
    }

    private function supplier(string $code): Supplier
    {
        return Supplier::query()->create([
            'code' => $code,
            'name' => 'Supplier '.$code,
            'status' => 'active',
        ]);
    }

    private function warehouse(string $code, string $type): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => 'Warehouse '.$code,
            'type' => $type,
            'status' => 'active',
        ]);
    }

    private function vehicleWarehouse(string $code): Warehouse
    {
        $vehicleId = DB::table('vehicles')->insertGetId([
            'code' => 'VEH-'.$code,
            'plate_number' => 'PLATE-'.$code,
            'name' => 'Vehicle '.$code,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Warehouse::query()->create([
            'vehicle_id' => $vehicleId,
            'code' => $code,
            'name' => 'Vehicle Warehouse '.$code,
            'type' => 'vehicle',
            'status' => 'active',
        ]);
    }

    private function product(string $sku, bool $hasExpiry): Product
    {
        return Product::query()->create([
            'sku' => $sku,
            'name_ar' => 'Product '.$sku,
            'purchase_price' => 10,
            'sale_price' => 20,
            'wholesale_price' => 15,
            'min_stock' => 0,
            'has_expiry' => $hasExpiry,
            'status' => 'active',
        ]);
    }

    private function order(
        Supplier $supplier,
        Warehouse $warehouse,
        Product $product,
        float $quantity,
        float $unitCost,
    ): PurchaseOrder {
        $order = PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'notes' => 'Purchasing workflow test',
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'ordered_quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]);

        return $order->refresh();
    }

    private function balance(Warehouse $warehouse, Product $product): StockBalance
    {
        return StockBalance::withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
    }
}
