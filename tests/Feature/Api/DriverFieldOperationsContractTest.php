<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionName;
use App\Support\Api\MobileSyncEntityRegistry;
use App\Support\Api\MobileSyncPushRegistry;
use App\Support\Authorization\RolePermissionMap;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DriverFieldOperationsContractTest extends TestCase
{
    #[Test]
    public function driver_field_entities_are_exposed_for_pull_and_push(): void
    {
        $this->assertArrayHasKey('driver_journeys', MobileSyncEntityRegistry::definitions());
        $this->assertArrayHasKey('driver_deliveries', MobileSyncEntityRegistry::definitions());
        $this->assertTrue(MobileSyncPushRegistry::supports('driver_journeys', 'start'));
        $this->assertTrue(MobileSyncPushRegistry::supports('driver_journeys', 'finish'));
        $this->assertTrue(MobileSyncPushRegistry::supports('driver_deliveries', 'submit_outcome'));
    }

    #[Test]
    public function operational_bootstrap_exposes_driver_field_modules_and_actions(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Api/V1/Operational/OperationalBootstrapController.php'),
        );

        $this->assertIsString($controller);
        $this->assertStringContainsString("'driver_journeys'", $controller);
        $this->assertStringContainsString("'driver_deliveries'", $controller);
        $this->assertStringContainsString('DRIVER_JOURNEYS_OPEN', $controller);
        $this->assertStringContainsString('DRIVER_DELIVERIES_SUBMIT_OUTCOME', $controller);
    }

    #[Test]
    public function driver_role_has_only_the_required_field_operation_mutations(): void
    {
        $permissions = RolePermissionMap::all()['driver'];

        $this->assertContains(PermissionName::DRIVER_JOURNEYS_VIEW->value, $permissions);
        $this->assertContains(PermissionName::DRIVER_JOURNEYS_OPEN->value, $permissions);
        $this->assertContains(PermissionName::DRIVER_JOURNEYS_START->value, $permissions);
        $this->assertContains(PermissionName::DRIVER_JOURNEYS_FINISH->value, $permissions);
        $this->assertContains(PermissionName::DRIVER_DELIVERIES_VIEW->value, $permissions);
        $this->assertContains(PermissionName::DRIVER_DELIVERIES_SUBMIT_OUTCOME->value, $permissions);
        $this->assertContains(PermissionName::CUSTOMERS_VIEW->value, $permissions);

        $salesPermissions = RolePermissionMap::all()['sales_representative'];
        $this->assertContains(PermissionName::DRIVER_JOURNEYS_VIEW->value, $salesPermissions);
        $this->assertContains(PermissionName::DRIVER_DELIVERIES_VIEW->value, $salesPermissions);
    }
}
