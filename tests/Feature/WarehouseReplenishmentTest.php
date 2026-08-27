<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\AdministrativeStockMovementService;
use App\Services\Inventory\WarehouseReplenishmentService;
use App\Services\Inventory\WarehouseStockAlertService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class WarehouseReplenishmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->syncStockMovementSequenceForToday();
        $this->actingAs($this->makeUser(User::ROLE_SUPER_ADMIN));
    }

    public function test_stock_receipt_replenishes_main_warehouse_and_updates_weighted_average(): void
    {
        $warehouse = $this->makeWarehouse('WH-RCV', 'main');
        $product = $this->makeProduct('PRD-RCV', 5, false, 100);
        $service = app(WarehouseReplenishmentService::class);

        $first = $service->receive(
            warehouse: $warehouse,
            product: $product,
            quantity: 10,
            unitCost: 100,
            notes: 'Supplier invoice INV-1001',
        );

        $second = $service->receive(
            warehouse: $warehouse,
            product: $product,
            quantity: 10,
            unitCost: 200,
            notes: 'Supplier invoice INV-1002',
        );

        $balance = $this->balance($warehouse, $product);

        $this->assertSame('stock_receipt', $first->movement_type);
        $this->assertSame('stock_receipt', $second->movement_type);
        $this->assertEqualsWithDelta(20, (float) $balance->quantity, 0.0001);
        $this->assertEqualsWithDelta(150, (float) $balance->average_unit_cost, 0.000001);
        $this->assertEqualsWithDelta(2000, (float) $second->total_cost, 0.001);
    }

    public function test_replenishment_and_warehouse_transfer_cannot_bypass_vehicle_load_workflow(): void
    {
        $main = $this->makeWarehouse('WH-MAIN', 'main');
        $branch = $this->makeWarehouse('WH-BRANCH', 'branch');
        $vehicleWarehouse = $this->makeVehicleWarehouse('WH-VEH');
        $product = $this->makeProduct('PRD-VEH', 3, false, 50);
        $service = app(WarehouseReplenishmentService::class);

        $service->receive(
            warehouse: $main,
            product: $product,
            quantity: 12,
            unitCost: 50,
            notes: 'Supplier receipt for fixed warehouse',
        );

        $transfer = $service->transfer(
            fromWarehouse: $main,
            toWarehouse: $branch,
            product: $product,
            quantity: 4,
            notes: 'Branch replenishment transfer',
        );

        $this->assertSame('warehouse_transfer', $transfer->movement_type);
        $this->assertEqualsWithDelta(8, (float) $this->balance($main, $product)->quantity, 0.0001);
        $this->assertEqualsWithDelta(4, (float) $this->balance($branch, $product)->quantity, 0.0001);
        $this->assertEqualsWithDelta(50, (float) $this->balance($branch, $product)->average_unit_cost, 0.000001);

        try {
            $service->transfer(
                fromWarehouse: $main,
                toWarehouse: $vehicleWarehouse,
                product: $product,
                quantity: 1,
                notes: 'Attempt to bypass vehicle load workflow',
            );
            $this->fail('Expected vehicle warehouse transfer to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('حمولة السيارة', $exception->getMessage());
        }

        $this->assertEqualsWithDelta(8, (float) $this->balance($main, $product)->quantity, 0.0001);
        $this->assertDatabaseMissing('stock_balances', [
            'warehouse_id' => $vehicleWarehouse->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_stock_receipt_obeys_expiry_policy(): void
    {
        $warehouse = $this->makeWarehouse('WH-EXP', 'main');
        $product = $this->makeProduct('PRD-EXP', 2, true, 75);
        $service = app(WarehouseReplenishmentService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('يتطلب تاريخ صلاحية');

        $service->receive(
            warehouse: $warehouse,
            product: $product,
            quantity: 5,
            unitCost: 75,
            batchNumber: 'LOT-EXP-01',
            notes: 'Receipt missing expiry date',
        );
    }

    public function test_stock_receipt_can_be_reversed_without_deleting_original_movement(): void
    {
        $warehouse = $this->makeWarehouse('WH-REV-RCV', 'main');
        $product = $this->makeProduct('PRD-REV-RCV', 2, false, 75);

        $original = app(WarehouseReplenishmentService::class)->receive(
            warehouse: $warehouse,
            product: $product,
            quantity: 5,
            unitCost: 75,
            notes: 'Supplier receipt to reverse later',
        );

        $reversal = app(AdministrativeStockMovementService::class)->reverse(
            movement: $original,
            reason: 'Supplier receipt was entered by mistake',
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $original->id,
            'movement_type' => 'stock_receipt',
            'quantity' => 5,
        ]);
        $this->assertSame('administrative_reversal', $reversal->movement_type);
        $this->assertSame(StockMovement::class, $reversal->reference_type);
        $this->assertSame($original->id, $reversal->reference_id);
        $this->assertEqualsWithDelta(0, (float) $this->balance($warehouse, $product)->quantity, 0.0001);
    }

    public function test_main_warehouse_alert_summary_detects_stockout_low_and_healthy_products(): void
    {
        $warehouse = $this->makeWarehouse('WH-ALERT', 'main');
        $stockout = $this->makeProduct('PRD-OUT', 5, false, 10);
        $low = $this->makeProduct('PRD-LOW', 5, false, 10);
        $healthy = $this->makeProduct('PRD-OK', 5, false, 10);
        $service = app(WarehouseReplenishmentService::class);

        $service->receive(
            warehouse: $warehouse,
            product: $low,
            quantity: 3,
            unitCost: 10,
            notes: 'Low stock test receipt',
        );

        $service->receive(
            warehouse: $warehouse,
            product: $healthy,
            quantity: 8,
            unitCost: 10,
            notes: 'Healthy stock test receipt',
        );

        $summary = app(WarehouseStockAlertService::class)->mainWarehouseSummary();

        $this->assertSame(1, $summary['out_of_stock']);
        $this->assertSame(1, $summary['low_stock']);
        $this->assertSame(1, $summary['healthy']);
        $this->assertSame(3, $summary['total']);
        $this->assertDatabaseMissing('stock_balances', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $stockout->id,
        ]);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function makeWarehouse(string $code, string $type): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => 'Warehouse '.$code,
            'type' => $type,
            'status' => 'active',
        ]);
    }

    private function makeVehicleWarehouse(string $code): Warehouse
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

    private function makeProduct(
        string $sku,
        float $minStock,
        bool $hasExpiry,
        float $purchasePrice,
    ): Product {
        return Product::query()->create([
            'sku' => $sku,
            'name_ar' => 'Product '.$sku,
            'purchase_price' => $purchasePrice,
            'sale_price' => $purchasePrice + 10,
            'wholesale_price' => $purchasePrice + 5,
            'min_stock' => $minStock,
            'has_expiry' => $hasExpiry,
            'status' => 'active',
        ]);
    }

    private function balance(Warehouse $warehouse, Product $product): StockBalance
    {
        return StockBalance::withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->firstOrFail();
    }

    private function syncStockMovementSequenceForToday(): void
    {
        DB::table('document_sequences')->updateOrInsert(
            [
                'document_type' => 'stock_movement',
                'sequence_date' => now()->toDateString(),
            ],
            [
                'last_number' => 990000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
