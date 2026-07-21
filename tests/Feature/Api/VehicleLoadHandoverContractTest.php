<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class VehicleLoadHandoverContractTest extends TestCase
{
    public function test_vehicle_load_handover_is_wired_into_rest_and_offline_push_contracts(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $registry = file_get_contents(app_path('Support/Api/MobileSyncPushRegistry.php'));
        $push = file_get_contents(app_path('Services/Api/MobileSyncPushOperationService.php'));
        $resource = file_get_contents(app_path('Http/Resources/Api/V1/Operational/VehicleLoadResource.php'));
        $service = file_get_contents(app_path('Services/Distribution/VehicleLoadHandoverService.php'));

        $this->assertStringContainsString("/vehicle-loads/{vehicleLoad}/acknowledge", $routes);
        $this->assertStringContainsString("'vehicle_loads' =>", $registry);
        $this->assertStringContainsString("'actions' => ['acknowledge']", $registry);
        $this->assertStringContainsString("['vehicle_loads', 'acknowledge']", $push);
        $this->assertStringContainsString("'handover_status'", $resource);
        $this->assertStringContainsString('received_quantity', $service);
        $this->assertStringContainsString('لا يغير', file_get_contents(base_path('docs/api/MOBILE_VEHICLE_LOAD_HANDOVER_PHASE10_AR.md')));
    }

    public function test_handover_migration_preserves_inventory_transfer_responsibility(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_07_21_140000_add_vehicle_load_handover_fields.php'));
        $service = file_get_contents(app_path('Services/Distribution/VehicleLoadHandoverService.php'));

        $this->assertStringContainsString('handover_status', $migration);
        $this->assertStringContainsString('received_quantity', $migration);
        $this->assertStringNotContainsString('InventoryMovementService', $service);
        $this->assertStringNotContainsString('transfer(', $service);
    }
}
