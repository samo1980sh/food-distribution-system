<?php

namespace Tests\Feature;

use Tests\TestCase;

class InventoryWorkspaceNavigationTest extends TestCase
{
    public function test_inventory_resources_are_grouped_in_one_top_tab_cluster(): void
    {
        $cluster = file_get_contents(app_path('Filament/Clusters/InventoryCluster.php'));
        $balances = file_get_contents(app_path('Filament/Resources/StockBalances/StockBalanceResource.php'));
        $movements = file_get_contents(app_path('Filament/Resources/StockMovements/StockMovementResource.php'));
        $panel = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        $this->assertStringContainsString("return 'إدارة المخزون';", $cluster);
        $this->assertStringContainsString('SubNavigationPosition::Top', $cluster);
        $this->assertStringContainsString('protected static ?string $cluster = InventoryCluster::class;', $balances);
        $this->assertStringContainsString('protected static ?string $cluster = InventoryCluster::class;', $movements);
        $this->assertStringContainsString("return 'المخزون الحالي';", $balances);
        $this->assertStringContainsString("return 'سجل حركات المخزون';", $movements);
        $this->assertStringContainsString('->discoverClusters(', $panel);
    }

    public function test_inventory_business_logic_remains_in_existing_resources_and_services(): void
    {
        $movementsPage = file_get_contents(app_path('Filament/Resources/StockMovements/Pages/ManageStockMovements.php'));
        $inventoryService = file_get_contents(app_path('Services/Inventory/InventoryMovementService.php'));
        $purchaseOrderTable = file_get_contents(app_path('Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php'));
        $transferPage = file_get_contents(app_path('Filament/Pages/WarehouseTransfer.php'));

        foreach ([
            "Action::make('createInventoryAdjustment')",
            'InventoryAdjustmentService::class',
        ] as $expected) {
            $this->assertStringContainsString($expected, $movementsPage);
        }

        $this->assertStringContainsString("Action::make('transferWarehouseStock')", $transferPage);
        $this->assertStringContainsString('WarehouseReplenishmentService::class', $transferPage);
        $this->assertStringNotContainsString("Action::make('receiveStock')", $movementsPage);
        $this->assertStringContainsString("Action::make('receive')", $purchaseOrderTable);
        $this->assertStringContainsString('PurchaseOrderService::class', $purchaseOrderTable);
        $this->assertFileDoesNotExist(app_path('Filament/Resources/StockMovements/Schemas/StockMovementForm.php'));
        $this->assertStringContainsString('class InventoryMovementService', $inventoryService);
    }

    public function test_dashboard_inventory_links_use_resource_generated_url(): void
    {
        $dashboard = file_get_contents(app_path('Services/Dashboard/ExecutiveDashboardService.php'));

        $this->assertStringContainsString('use App\\Filament\\Resources\\StockBalances\\StockBalanceResource;', $dashboard);
        $this->assertStringContainsString("StockBalanceResource::getUrl('index')", $dashboard);
        $this->assertStringNotContainsString('filament.admin.resources.stock-balances.index', $dashboard);
    }
}
