<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\User;
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
        $this->assertStringContainsString("Action::make('cancel')", $table);
        $this->assertStringContainsString("->label('أمر شراء جديد')", $page);
        $this->assertStringContainsString("movementType: 'stock_receipt'", file_get_contents(
            app_path('Services/Inventory/WarehouseReplenishmentService.php'),
        ));
        $this->assertStringContainsString('reference: $receipt', $service);
        $this->assertStringContainsString('STATUS_PARTIALLY_RECEIVED', $service);
        $this->assertStringContainsString('STATUS_RECEIVED', $service);
    }
}
