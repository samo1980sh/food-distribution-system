<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\AdministrativeStockMovementService;
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
        $this->assertFileDoesNotExist(resource_path('views/filament/inventory/stock-movement-details.blade.php'));
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
