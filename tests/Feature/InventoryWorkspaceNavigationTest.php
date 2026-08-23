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
        $this->assertStringContainsString("return 'الحركات والتسويات';", $movements);
        $this->assertStringContainsString('->discoverClusters(', $panel);
    }

    public function test_inventory_business_logic_remains_in_existing_resources_and_services(): void
    {
        $movementsPage = file_get_contents(app_path('Filament/Resources/StockMovements/Pages/ManageStockMovements.php'));
        $movementForm = file_get_contents(app_path('Filament/Resources/StockMovements/Schemas/StockMovementForm.php'));
        $inventoryService = file_get_contents(app_path('Services/Inventory/InventoryMovementService.php'));

        foreach ([
            "'opening_balance' => \$service->addStock(",
            "'manual_out' => \$service->removeStock(",
            "'warehouse_transfer' => \$service->transfer(",
        ] as $expected) {
            $this->assertStringContainsString($expected, $movementsPage);
        }

        $this->assertStringContainsString("'opening_balance' => 'رصيد افتتاحي / إدخال'", $movementForm);
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
