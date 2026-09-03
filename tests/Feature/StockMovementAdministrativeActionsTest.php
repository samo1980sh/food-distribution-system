<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\AdministrativeStockMovementService;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\InventoryMovementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StockMovementAdministrativeActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->syncStockMovementSequenceForToday();
        $this->actingAs($this->makeUser(User::ROLE_SUPER_ADMIN));
    }

    public function test_opening_balance_can_be_reversed_without_deleting_original_movement(): void
    {
        $warehouse = $this->makeWarehouse('WH-REV');
        $product = $this->makeProduct('PRD-REV', false, 100);

        $original = app(InventoryMovementService::class)->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 10,
            unitCost: 100,
            movementType: 'opening_balance',
            notes: 'Opening balance for reversal test',
        );

        $reversal = app(AdministrativeStockMovementService::class)->reverse(
            movement: $original,
            reason: 'Correction required after physical recount',
            movementDate: now()->toDateString(),
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $original->id,
            'movement_type' => 'opening_balance',
            'quantity' => 10,
        ]);
        $this->assertSame('administrative_reversal', $reversal->movement_type);
        $this->assertSame(StockMovement::class, $reversal->reference_type);
        $this->assertSame($original->id, $reversal->reference_id);
        $this->assertEqualsWithDelta(0, (float) $this->balance($warehouse, $product)->quantity, 0.0001);

        $this->expectException(RuntimeException::class);
        app(AdministrativeStockMovementService::class)->reverse(
            movement: $original,
            reason: 'Second reversal must never be allowed',
        );
    }

    public function test_correction_reverses_original_and_creates_new_corrected_movement_atomically(): void
    {
        $warehouse = $this->makeWarehouse('WH-CORR');
        $product = $this->makeProduct('PRD-CORR', false, 100);

        $original = app(InventoryMovementService::class)->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 10,
            unitCost: 100,
            movementType: 'opening_balance',
            notes: 'Opening balance before correction',
        );

        $result = app(AdministrativeStockMovementService::class)->correct(
            movement: $original,
            corrected: [
                'movement_date' => now()->toDateString(),
                'product_id' => $product->id,
                'to_warehouse_id' => $warehouse->id,
                'batch_number' => null,
                'expiry_date' => null,
                'quantity' => 7,
                'unit_cost' => 120,
            ],
            reason: 'Physical count confirmed seven units only',
        );

        $original->refresh();
        $balance = $this->balance($warehouse, $product);

        $this->assertEqualsWithDelta(10, (float) $original->quantity, 0.0001);
        $this->assertSame('administrative_reversal', $result['reversal']->movement_type);
        $this->assertSame('opening_balance', $result['corrected']->movement_type);
        $this->assertSame(StockMovement::class, $result['corrected']->reference_type);
        $this->assertSame($original->id, $result['corrected']->reference_id);
        $this->assertEqualsWithDelta(7, (float) $balance->quantity, 0.0001);
        $this->assertEqualsWithDelta(120, (float) $balance->average_unit_cost, 0.000001);
        $this->assertSame(3, StockMovement::query()->count());
    }

    public function test_operational_movement_cannot_be_reversed_from_inventory_ledger(): void
    {
        $warehouse = $this->makeWarehouse('WH-OPS');
        $product = $this->makeProduct('PRD-OPS', false, 50);

        $movement = StockMovement::query()->create([
            'movement_number' => 'STM-OPS-001',
            'movement_type' => 'sales_invoice',
            'movement_date' => now()->toDateString(),
            'from_warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_cost' => 50,
            'total_cost' => 100,
            'notes' => 'Operational movement for guard test',
        ]);

        $service = app(AdministrativeStockMovementService::class);

        $this->assertFalse($service->canActOn($movement));

        $this->expectException(RuntimeException::class);
        $service->reverse(
            movement: $movement,
            reason: 'Operational records must be cancelled at source',
        );
    }

    public function test_positive_and_negative_adjustments_use_explicit_types_and_safe_costing(): void
    {
        $warehouse = $this->makeWarehouse('WH-ADJ');
        $product = $this->makeProduct('PRD-ADJ', false, 100);
        app(InventoryMovementService::class)->addStock($warehouse, $product, 10, unitCost: 100, notes: 'Opening stock for adjustment');

        $service = app(InventoryAdjustmentService::class);
        $increase = $service->create($warehouse, $product, 'in', 10, 'discovered_surplus', 'Physical surplus confirmed in documented count', 200);
        $decrease = $service->create($warehouse, $product, 'out', 5, 'damage', 'Damaged units confirmed by warehouse manager');

        $this->assertSame('inventory_adjustment_in', $increase->movement_type);
        $this->assertSame('inventory_adjustment_out', $decrease->movement_type);
        $this->assertEqualsWithDelta(15, (float) $this->balance($warehouse, $product)->quantity, 0.0001);
        $this->assertEqualsWithDelta(150, (float) $this->balance($warehouse, $product)->average_unit_cost, 0.000001);

        $this->expectException(RuntimeException::class);
        $service->create($warehouse, $product, 'out', 16, 'loss', 'Attempt to remove more stock than currently available');
    }

    public function test_adjustment_requires_permission_reason_and_positive_inbound_cost(): void
    {
        $warehouse = $this->makeWarehouse('WH-AUTH');
        $product = $this->makeProduct('PRD-AUTH', false, 100);
        $service = app(InventoryAdjustmentService::class);

        foreach ([User::ROLE_WAREHOUSE_KEEPER, User::ROLE_SUPERVISOR, User::ROLE_SALES_REPRESENTATIVE] as $role) {
            $this->actingAs($this->makeUser($role));

            try {
                $service->create($warehouse, $product, 'in', 1, 'discovered_surplus', 'Documented physical stock surplus', 100);
                $this->fail('Expected adjustment authorization rejection for '.$role);
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }

        $this->actingAs($this->makeUser(User::ROLE_MANAGER));

        $increase = $service->create($warehouse, $product, 'in', 2, 'discovered_surplus', 'Manager confirmed an actual physical surplus', 100);
        $decrease = $service->create($warehouse, $product, 'out', 1, 'inventory_variance', 'Manager confirmed a documented inventory variance');
        $this->assertSame('inventory_adjustment_in', $increase->movement_type);
        $this->assertSame('inventory_adjustment_out', $decrease->movement_type);

        $this->expectException(RuntimeException::class);
        $service->create($warehouse, $product, 'in', 1, 'discovered_surplus', 'short', 0);
    }

    public function test_new_adjustments_are_reversible_and_purchase_receipt_movement_is_protected(): void
    {
        $warehouse = $this->makeWarehouse('WH-REV-ADJ');
        $product = $this->makeProduct('PRD-REV-ADJ', false, 50);
        $adjustment = app(InventoryAdjustmentService::class)->create(
            $warehouse, $product, 'in', 4, 'discovered_surplus', 'Confirmed surplus for reversal coverage', 50,
        );

        $administration = app(AdministrativeStockMovementService::class);
        $this->assertTrue($administration->canActOn($adjustment));
        $administration->reverse($adjustment, 'Reversing documented adjustment after recount');
        $this->assertEqualsWithDelta(0, (float) $this->balance($warehouse, $product)->quantity, 0.0001);

        app(InventoryMovementService::class)->addStock($warehouse, $product, 5, unitCost: 50, notes: 'Stock for negative adjustment reversal');
        $negative = app(InventoryAdjustmentService::class)->create(
            $warehouse, $product, 'out', 2, 'damage', 'Documented damage later rejected during review',
        );
        $this->assertTrue($administration->canActOn($negative));
        $administration->reverse($negative, 'Restoring units after damage classification correction');
        $this->assertEqualsWithDelta(5, (float) $this->balance($warehouse, $product)->quantity, 0.0001);

        $receiptMovement = StockMovement::query()->create([
            'movement_number' => 'STM-PRC-PROTECTED',
            'movement_type' => 'stock_receipt',
            'movement_date' => now()->toDateString(),
            'reference_type' => PurchaseReceipt::class,
            'reference_id' => 999,
            'to_warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_cost' => 50,
            'total_cost' => 50,
        ]);

        $this->assertFalse($administration->canActOn($receiptMovement));
        $this->expectException(RuntimeException::class);
        $administration->reverse($receiptMovement, 'Purchase receipt must remain authoritative');
    }

    public function test_inbound_adjustment_reversal_removes_its_original_cost_basis(): void
    {
        $warehouse = $this->makeWarehouse('WH-IN-COST');
        $product = $this->makeProduct('PRD-IN-COST', false, 100);
        $inventory = app(InventoryMovementService::class);

        $inventory->addStock($warehouse, $product, 10, unitCost: 100, notes: 'Opening stock');
        $adjustment = app(InventoryAdjustmentService::class)->create(
            $warehouse, $product, 'in', 10, 'discovered_surplus', 'Documented surplus at its actual cost', 200,
        );

        $reversal = app(AdministrativeStockMovementService::class)->reverse(
            $adjustment,
            'Reverse the documented inbound adjustment',
        );
        $balance = $this->balance($warehouse, $product);

        $this->assertEqualsWithDelta(10, (float) $balance->quantity, 0.0001);
        $this->assertEqualsWithDelta(100, (float) $balance->average_unit_cost, 0.000001);
        $this->assertEqualsWithDelta(200, (float) $reversal->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(2000, (float) $reversal->total_cost, 0.000001);
        $this->assertSame(StockMovement::class, $reversal->reference_type);
        $this->assertSame($adjustment->id, $reversal->reference_id);
        $this->assertDatabaseHas('stock_movements', [
            'id' => $adjustment->id,
            'movement_type' => 'inventory_adjustment_in',
            'quantity' => 10,
        ]);

        $this->expectException(RuntimeException::class);
        app(AdministrativeStockMovementService::class)->reverse(
            $adjustment,
            'Second adjustment reversal must remain blocked',
        );
    }

    public function test_inbound_adjustment_reversal_preserves_value_of_later_inbound_stock(): void
    {
        $warehouse = $this->makeWarehouse('WH-LATER-IN');
        $product = $this->makeProduct('PRD-LATER-IN', false, 100);
        $inventory = app(InventoryMovementService::class);

        $inventory->addStock($warehouse, $product, 10, unitCost: 100, notes: 'Opening stock');
        $adjustment = app(InventoryAdjustmentService::class)->create(
            $warehouse, $product, 'in', 10, 'discovered_surplus', 'Documented intermediate surplus', 200,
        );
        $inventory->addStock($warehouse, $product, 10, unitCost: 300, notes: 'Later inbound stock');

        app(AdministrativeStockMovementService::class)->reverse(
            $adjustment,
            'Reverse only the intermediate inbound adjustment',
        );
        $balance = $this->balance($warehouse, $product);

        $this->assertEqualsWithDelta(20, (float) $balance->quantity, 0.0001);
        $this->assertEqualsWithDelta(200, (float) $balance->average_unit_cost, 0.000001);
    }

    public function test_inbound_adjustment_reversal_is_limited_to_exact_batch_and_expiry(): void
    {
        $warehouse = $this->makeWarehouse('WH-BATCH-REV');
        $product = $this->makeProduct('PRD-BATCH-REV', true, 100);
        $inventory = app(InventoryMovementService::class);

        $inventory->addStock($warehouse, $product, 5, 'BATCH-A', '2027-01-31', 100, notes: 'First batch');
        $adjustment = app(InventoryAdjustmentService::class)->create(
            $warehouse,
            $product,
            'in',
            3,
            'discovered_surplus',
            'Surplus tied to the first batch identity',
            200,
            'BATCH-A',
            '2027-01-31',
        );
        $inventory->addStock($warehouse, $product, 7, 'BATCH-B', '2027-02-28', 300, notes: 'Second batch');

        app(AdministrativeStockMovementService::class)->reverse($adjustment, 'Reverse the first batch surplus only');

        $first = StockBalance::withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('batch_key', 'BATCH-A')
            ->where('expiry_key', '2027-01-31')
            ->firstOrFail();
        $second = StockBalance::withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('batch_key', 'BATCH-B')
            ->where('expiry_key', '2027-02-28')
            ->firstOrFail();

        $this->assertEqualsWithDelta(5, (float) $first->quantity, 0.0001);
        $this->assertEqualsWithDelta(100, (float) $first->average_unit_cost, 0.000001);
        $this->assertEqualsWithDelta(7, (float) $second->quantity, 0.0001);
        $this->assertEqualsWithDelta(300, (float) $second->average_unit_cost, 0.000001);
    }

    public function test_inbound_adjustment_reversal_fails_atomically_when_quantity_or_value_is_insufficient(): void
    {
        $warehouse = $this->makeWarehouse('WH-FAIL-COST');
        $product = $this->makeProduct('PRD-FAIL-COST', false, 100);
        $inventory = app(InventoryMovementService::class);
        $administration = app(AdministrativeStockMovementService::class);

        $adjustment = app(InventoryAdjustmentService::class)->create(
            $warehouse, $product, 'in', 10, 'discovered_surplus', 'Inbound stock later partially consumed', 200,
        );
        $inventory->removeStock($warehouse, $product, 6, notes: 'Consume part of adjusted stock');
        $movementCount = StockMovement::withoutGlobalScopes()->count();

        try {
            $administration->reverse($adjustment, 'Cannot reverse more units than remain');
            $this->fail('Expected reversal to fail for insufficient quantity.');
        } catch (RuntimeException) {
            $this->assertEqualsWithDelta(4, (float) $this->balance($warehouse, $product)->quantity, 0.0001);
            $this->assertSame($movementCount, StockMovement::withoutGlobalScopes()->count());
        }

        $valueProduct = $this->makeProduct('PRD-FAIL-VALUE', false, 100);
        $inventory->addStock($warehouse, $valueProduct, 10, unitCost: 100, notes: 'Low-cost opening stock');
        $expensiveAdjustment = app(InventoryAdjustmentService::class)->create(
            $warehouse, $valueProduct, 'in', 10, 'discovered_surplus', 'High-cost surplus for value guard', 1000,
        );
        $inventory->removeStock($warehouse, $valueProduct, 10, notes: 'Consume pooled stock at weighted average');
        $movementCount = StockMovement::withoutGlobalScopes()->count();

        try {
            $administration->reverse($expensiveAdjustment, 'Cost basis can no longer be removed safely');
            $this->fail('Expected reversal to fail for insufficient remaining value.');
        } catch (RuntimeException) {
            $balance = $this->balance($warehouse, $valueProduct);
            $this->assertEqualsWithDelta(10, (float) $balance->quantity, 0.0001);
            $this->assertEqualsWithDelta(550, (float) $balance->average_unit_cost, 0.000001);
            $this->assertSame($movementCount, StockMovement::withoutGlobalScopes()->count());
        }
    }

    public function test_outbound_reversal_and_inbound_correction_keep_expected_valuation(): void
    {
        $warehouse = $this->makeWarehouse('WH-CORR-COST');
        $outProduct = $this->makeProduct('PRD-OUT-COST', false, 100);
        $inventory = app(InventoryMovementService::class);
        $adjustments = app(InventoryAdjustmentService::class);
        $administration = app(AdministrativeStockMovementService::class);

        $inventory->addStock($warehouse, $outProduct, 10, unitCost: 100, notes: 'Opening stock');
        $out = $adjustments->create($warehouse, $outProduct, 'out', 4, 'damage', 'Documented outbound adjustment');
        $inventory->addStock($warehouse, $outProduct, 6, unitCost: 200, notes: 'Later inbound stock');
        $administration->reverse($out, 'Restore outbound units at their original cost');

        $outBalance = $this->balance($warehouse, $outProduct);
        $this->assertEqualsWithDelta(16, (float) $outBalance->quantity, 0.0001);
        $this->assertEqualsWithDelta(137.5, (float) $outBalance->average_unit_cost, 0.000001);

        $inProduct = $this->makeProduct('PRD-CORR-IN', false, 100);
        $inventory->addStock($warehouse, $inProduct, 10, unitCost: 100, notes: 'Opening stock');
        $in = $adjustments->create(
            $warehouse, $inProduct, 'in', 10, 'discovered_surplus', 'Original inbound adjustment', 200,
        );
        $result = $administration->correct($in, [
            'movement_date' => now()->toDateString(),
            'product_id' => $inProduct->id,
            'to_warehouse_id' => $warehouse->id,
            'batch_number' => null,
            'expiry_date' => null,
            'quantity' => 5,
            'unit_cost' => 300,
        ], 'Correct inbound quantity and original unit cost');

        $inBalance = $this->balance($warehouse, $inProduct);
        $this->assertEqualsWithDelta(15, (float) $inBalance->quantity, 0.0001);
        $this->assertEqualsWithDelta(166.666667, (float) $inBalance->average_unit_cost, 0.000001);
        $this->assertEqualsWithDelta(200, (float) $result['reversal']->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(300, (float) $result['corrected']->unit_cost, 0.000001);
        $this->assertDatabaseHas('stock_movements', [
            'id' => $in->id,
            'movement_type' => 'inventory_adjustment_in',
            'quantity' => 10,
        ]);
    }

    public function test_restricted_user_cannot_reverse_movement_outside_assigned_warehouse_scope(): void
    {
        $allowed = $this->makeWarehouse('WH-ALLOWED');
        $blocked = $this->makeWarehouse('WH-BLOCKED');
        $product = $this->makeProduct('PRD-SCOPE', false, 25);

        $original = app(InventoryMovementService::class)->addStock(
            warehouse: $blocked,
            product: $product,
            quantity: 5,
            unitCost: 25,
            movementType: 'opening_balance',
            notes: 'Opening balance outside restricted scope',
        );

        $keeper = $this->makeUser(User::ROLE_WAREHOUSE_KEEPER);
        $keeper->accessWarehouses()->sync([$allowed->id]);
        $this->actingAs($keeper);

        try {
            app(AdministrativeStockMovementService::class)->reverse(
                movement: $original,
                reason: 'Attempted reversal outside current warehouse scope',
            );
            $this->fail('Expected the out-of-scope reversal to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('خارج نطاق وصول المستخدم الحالي', $exception->getMessage());
        }

        $this->assertEqualsWithDelta(5, (float) $this->balance($blocked, $product)->quantity, 0.0001);
        $this->assertSame(1, StockMovement::withoutGlobalScopes()->count());
    }

    public function test_inventory_movement_page_renders_with_actions_configuration(): void
    {
        $this->get('/admin/inventory/stock-movements')->assertOk();
    }

    public function test_filament_table_exposes_native_slideover_audit_actions_without_direct_edit_or_delete(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/StockMovements/Tables/StockMovementsTable.php'));

        $this->assertIsString($table);
        $this->assertStringContainsString("Action::make('viewMovementDetails')", $table);
        $this->assertStringContainsString("Action::make('correctAdministrativeMovement')", $table);
        $this->assertStringContainsString("Action::make('reverseAdministrativeMovement')", $table);
        $this->assertStringContainsString("Action::make('openOriginalReference')", $table);
        $this->assertGreaterThanOrEqual(3, substr_count($table, '->slideOver()'));
        $this->assertStringContainsString('TextEntry::make', $table);
        $this->assertStringContainsString('Section::make', $table);
        $this->assertStringContainsString('administrative_reversal', $table);
        $this->assertStringNotContainsString('->modalContent(', $table);
        $this->assertStringNotContainsString('filament.inventory.stock-movement-details', $table);
        $this->assertStringNotContainsString('EditAction::make', $table);
        $this->assertStringNotContainsString('DeleteAction::make', $table);
        $this->assertStringContainsString("Gate::allows('createAdjustment', \$record)", $table);
        $this->assertStringNotContainsString('StockMovementResource::canCreate()', $table);
        $this->assertFileDoesNotExist(resource_path('views/filament/inventory/stock-movement-details.blade.php'));
    }

    public function test_stock_movement_ui_uses_operation_permissions_instead_of_generic_create(): void
    {
        $page = file_get_contents(app_path('Filament/Resources/StockMovements/Pages/ManageStockMovements.php'));
        $resource = file_get_contents(app_path('Filament/Resources/StockMovements/StockMovementResource.php'));

        $this->assertIsString($page);
        $this->assertIsString($resource);
        $transferPage = file_get_contents(app_path('Filament/Pages/WarehouseTransfer.php'));
        $this->assertStringContainsString("Action::make('transferWarehouseStock')", $transferPage);
        $this->assertStringContainsString("Gate::allows('createAdjustment', StockMovement::class)", $page);
        $this->assertStringContainsString("Action::make('createInventoryAdjustment')", $page);
        $this->assertStringNotContainsString('CreateAction::make()', $page);
        $this->assertFileDoesNotExist(app_path('Filament/Resources/StockMovements/Schemas/StockMovementForm.php'));
        $this->assertStringNotContainsString("Action::make('receiveStock')", $page);
        $this->assertStringNotContainsString('receiptSchema', $page);
        $this->assertStringContainsString("return 'سجل حركات المخزون';", $resource);
        $this->assertStringNotContainsString("'create' =>", $resource);
        $this->assertStringNotContainsString('StockMovementResource::canCreate()', $page);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function makeWarehouse(string $code): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => 'Warehouse '.$code,
            'type' => 'main',
            'status' => 'active',
        ]);
    }

    private function makeProduct(string $sku, bool $hasExpiry, float $purchasePrice): Product
    {
        return Product::query()->create([
            'sku' => $sku,
            'name_ar' => 'Product '.$sku,
            'purchase_price' => $purchasePrice,
            'sale_price' => $purchasePrice + 10,
            'wholesale_price' => $purchasePrice + 5,
            'min_stock' => 0,
            'has_expiry' => $hasExpiry,
            'status' => 'active',
        ]);
    }

    private function balance(Warehouse $warehouse, Product $product): StockBalance
    {
        return StockBalance::withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('batch_key', '')
            ->where('expiry_key', '')
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
                'last_number' => 980000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
