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
    public function legacy_driver_sync_definitions_are_retained_only_for_retirement_compatibility(): void
    {
        $this->assertArrayHasKey('driver_journeys', MobileSyncEntityRegistry::definitions());
        $this->assertArrayHasKey('driver_deliveries', MobileSyncEntityRegistry::definitions());
        $this->assertArrayHasKey('driver_journeys', MobileSyncPushRegistry::definitions());
        $this->assertArrayHasKey('driver_deliveries', MobileSyncPushRegistry::definitions());

        $service = file_get_contents(
            app_path('Services/Api/MobileSyncPushOperationService.php'),
        );

        $this->assertIsString($service);
        $this->assertStringContainsString('representative_driver_workflow_retired', $service);
        $this->assertStringNotContainsString('DriverFieldOperationService', $service);
        $this->assertStringNotContainsString('StartDriverJourneyRequest', $service);
        $this->assertStringNotContainsString('FinishDriverJourneyRequest', $service);
        $this->assertStringNotContainsString('SubmitDriverDeliveryOutcomeRequest', $service);
    }

    #[Test]
    public function mobile_bootstrap_and_operational_service_do_not_expose_driver_runtime(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Api/V1/Operational/OperationalBootstrapController.php'),
        );
        $service = file_get_contents(
            app_path('Services/Api/MobileOperationalService.php'),
        );

        $this->assertIsString($controller);
        $this->assertIsString($service);

        foreach ([$controller, $service] as $source) {
            $this->assertStringNotContainsString("'driver_journeys'", $source);
            $this->assertStringNotContainsString("'driver_deliveries'", $source);
            $this->assertStringNotContainsString('DRIVER_JOURNEYS_OPEN', $source);
            $this->assertStringNotContainsString('DRIVER_DELIVERIES_SUBMIT_OUTCOME', $source);
        }
    }

    #[Test]
    public function legacy_driver_permissions_remain_deferred_to_admin_rbac_retirement(): void
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