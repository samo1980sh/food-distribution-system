<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingFilamentContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchasing_resources_use_inventory_rbac_and_preserve_stock_receipt_boundary(): void
    {
        $warehouseKeeper = User::factory()->create([
            'role' => User::ROLE_WAREHOUSE_KEEPER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $salesRepresentative = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->assertTrue($warehouseKeeper->can('viewAny', Supplier::class));
        $this->assertTrue($warehouseKeeper->can('create', Supplier::class));
        $this->assertTrue($warehouseKeeper->can('viewAny', PurchaseOrder::class));
        $this->assertTrue($warehouseKeeper->can('create', PurchaseOrder::class));
        $this->assertTrue($warehouseKeeper->can('viewAny', PurchaseReceipt::class));

        $this->assertFalse($salesRepresentative->can('create', Supplier::class));
        $this->assertFalse($salesRepresentative->can('create', PurchaseOrder::class));
    }

    public function test_only_operational_purchasing_resources_register_in_navigation(): void
    {
        $warehouseKeeper = User::factory()->create([
            'role' => User::ROLE_WAREHOUSE_KEEPER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($warehouseKeeper);

        $this->assertTrue(PurchaseOrderResource::shouldRegisterNavigation());
        $this->assertFalse(PurchaseReceiptResource::shouldRegisterNavigation());
        $this->assertTrue($warehouseKeeper->can('viewAny', PurchaseReceipt::class));
    }

    public function test_purchase_order_lifecycle_abilities_follow_role_state_and_warehouse_scope(): void
    {
        $allowedWarehouse = $this->warehouse('WH-ALLOWED');
        $blockedWarehouse = $this->warehouse('WH-BLOCKED');
        $supplier = Supplier::query()->create([
            'code' => 'SUP-RBAC',
            'name' => 'Supplier RBAC',
            'status' => 'active',
        ]);
        $draft = $this->order($supplier, $allowedWarehouse, PurchaseOrder::STATUS_DRAFT, 'PO-DRAFT');
        $approved = $this->order($supplier, $allowedWarehouse, PurchaseOrder::STATUS_APPROVED, 'PO-APPROVED');
        $outside = $this->order($supplier, $blockedWarehouse, PurchaseOrder::STATUS_DRAFT, 'PO-OUTSIDE');

        $keeper = User::factory()->create(['role' => User::ROLE_WAREHOUSE_KEEPER]);
        $keeper->accessWarehouses()->sync([$allowedWarehouse->id]);

        $this->assertTrue($keeper->can('update', $draft));
        $this->assertTrue($keeper->can('receive', $approved));
        $this->assertFalse($keeper->can('approve', $draft));
        $this->assertFalse($keeper->can('cancel', $approved));
        $this->assertFalse($keeper->can('view', $outside));
        $this->assertFalse($keeper->can('update', $outside));

        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $supervisor->accessWarehouses()->sync([$allowedWarehouse->id]);

        $this->assertTrue($supervisor->can('approve', $draft));
        $this->assertTrue($supervisor->can('cancel', $approved));
        $this->assertFalse($supervisor->can('receive', $approved));
        $this->assertFalse($supervisor->can('approve', $outside));

        $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);
        $this->assertTrue($accountant->can('view', $approved));
        $this->assertFalse($accountant->can('update', $draft));
        $this->assertFalse($accountant->can('approve', $draft));
        $this->assertFalse($accountant->can('receive', $approved));
        $this->assertFalse($accountant->can('cancel', $approved));
    }

    public function test_purchase_order_workspace_exposes_draft_approve_receive_and_cancel_lifecycle(): void
    {
        $table = file_get_contents(
            app_path('Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php'),
        );

        $page = file_get_contents(
            app_path('Filament/Resources/PurchaseOrders/Pages/ManagePurchaseOrders.php'),
        );

        $service = file_get_contents(
            app_path('Services/Purchasing/PurchaseOrderService.php'),
        );

        $this->assertIsString($table);
        $this->assertIsString($page);
        $this->assertIsString($service);

        $this->assertStringContainsString("Action::make('approve')", $table);
        $this->assertStringContainsString("Action::make('receive')", $table);
        $this->assertStringContainsString("Action::make('receiptHistory')", $table);
        $this->assertStringContainsString("Action::make('cancel')", $table);
        $this->assertStringContainsString("Gate::authorize('approve', \$record)", $table);
        $this->assertStringContainsString("Gate::authorize('receive', \$record)", $table);
        $this->assertStringContainsString("Gate::authorize('cancel', \$record)", $table);
        $this->assertStringNotContainsString('PurchaseOrderResource::canEdit', $table);
        $this->assertStringNotContainsString('PurchaseOrderResource::canView', $table);
        $this->assertStringContainsString("TextInput::make('ordered_quantity')", $table);
        $this->assertStringContainsString("TextInput::make('received_quantity')", $table);
        $this->assertStringContainsString("TextInput::make('remaining_quantity')", $table);
        $this->assertStringContainsString("TextInput::make('unit_cost')", $table);
        $this->assertStringContainsString("->maxValue(fn (\$get): float => (float) \$get('remaining_quantity'))", $table);
        $this->assertStringContainsString("'total_quantity' => round((float) \$receipt->items->sum('quantity'), 3)", $table);
        $this->assertStringContainsString("->label('أمر شراء جديد')", $page);
        $this->assertStringContainsString("return 'أوامر الشراء';", $page);
        $this->assertStringContainsString("movementType: 'stock_receipt'", file_get_contents(
            app_path('Services/Inventory/WarehouseReplenishmentService.php'),
        ));
        $this->assertStringContainsString('reference: $receipt', $service);
        $this->assertStringContainsString('STATUS_PARTIALLY_RECEIVED', $service);
        $this->assertStringContainsString('STATUS_RECEIVED', $service);
    }

    private function warehouse(string $code): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => 'Warehouse '.$code,
            'type' => 'main',
            'status' => 'active',
        ]);
    }

    private function order(
        Supplier $supplier,
        Warehouse $warehouse,
        string $status,
        string $number,
    ): PurchaseOrder {
        return PurchaseOrder::query()->create([
            'purchase_order_number' => $number,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => $status,
        ]);
    }
}
