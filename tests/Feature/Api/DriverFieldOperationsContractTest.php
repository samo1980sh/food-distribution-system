<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionName;
use App\Support\Api\MobileSyncEntityRegistry;
use App\Support\Api\MobileSyncPushRegistry;
use App\Support\Api\MobileSyncRetiredLegacyRegistry;
use App\Support\Authorization\RolePermissionMap;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DriverFieldOperationsContractTest extends TestCase
{
    #[Test]
    public function executable_driver_runtime_service_and_routes_are_removed(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Services/Distribution/DriverFieldOperationService.php'),
        );

        $routes = collect(Route::getRoutes()->getRoutes());

        $this->assertFalse($routes->contains(
            fn ($route): bool => str_contains($route->uri(), 'driver-journeys')
                || str_contains($route->uri(), 'driver-deliveries'),
        ));
        $this->assertFalse($routes->contains(
            fn ($route): bool => str_contains($route->getActionName(), 'DriverJourney')
                || str_contains($route->getActionName(), 'DriverDelivery'),
        ));
    }

    #[Test]
    public function legacy_driver_sync_definitions_are_retained_only_for_retirement_compatibility(): void
    {
        $this->assertSame(8, MobileSyncEntityRegistry::VERSION);
        $this->assertSame(5, MobileSyncPushRegistry::VERSION);
        $this->assertArrayNotHasKey('driver_journeys', MobileSyncEntityRegistry::definitions());
        $this->assertArrayNotHasKey('driver_deliveries', MobileSyncEntityRegistry::definitions());
        $this->assertArrayNotHasKey('driver_journeys', MobileSyncPushRegistry::definitions());
        $this->assertArrayNotHasKey('driver_deliveries', MobileSyncPushRegistry::definitions());

        $this->assertSame(
            ['driver_journeys', 'driver_deliveries'],
            MobileSyncRetiredLegacyRegistry::entities(),
        );
        $this->assertTrue(MobileSyncRetiredLegacyRegistry::supports('driver_journeys', 'start'));
        $this->assertTrue(MobileSyncRetiredLegacyRegistry::supports('driver_journeys', 'finish'));
        $this->assertTrue(MobileSyncRetiredLegacyRegistry::supports('driver_deliveries', 'submit_outcome'));
        $this->assertFalse(MobileSyncRetiredLegacyRegistry::supports('driver_journeys', 'open_today'));
        $this->assertContains('driver_journeys', MobileSyncPushRegistry::entities());
        $this->assertContains('driver_deliveries', MobileSyncPushRegistry::entities());
        $this->assertTrue(MobileSyncPushRegistry::supports('driver_journeys', 'start'));
        $this->assertTrue(MobileSyncPushRegistry::supports('driver_deliveries', 'submit_outcome'));

        $entityRegistry = file_get_contents(
            app_path('Support/Api/MobileSyncEntityRegistry.php'),
        );
        $pushRegistry = file_get_contents(
            app_path('Support/Api/MobileSyncPushRegistry.php'),
        );

        $service = file_get_contents(
            app_path('Services/Api/MobileSyncPushOperationService.php'),
        );

        $this->assertIsString($entityRegistry);
        $this->assertIsString($pushRegistry);
        $this->assertIsString($service);
        $this->assertStringNotContainsString('DriverJourneyResource', $entityRegistry);
        $this->assertStringNotContainsString('DriverDeliveryResource', $entityRegistry);
        $this->assertStringNotContainsString('DriverJourney::class', $entityRegistry);
        $this->assertStringNotContainsString('DriverDelivery::class', $entityRegistry);
        $this->assertStringNotContainsString('StartDriverJourneyRequest', $pushRegistry);
        $this->assertStringNotContainsString('SubmitDriverDeliveryOutcomeRequest', $pushRegistry);
        $this->assertStringNotContainsString('DriverJourney::class', $pushRegistry);
        $this->assertStringNotContainsString('DriverDelivery::class', $pushRegistry);
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
    public function legacy_driver_permissions_are_read_only_after_admin_rbac_retirement(): void
    {
        $permissions = RolePermissionMap::all()['driver'];

        $this->assertContains(PermissionName::DRIVER_JOURNEYS_VIEW->value, $permissions);
        $this->assertNotContains(PermissionName::DRIVER_JOURNEYS_OPEN->value, $permissions);
        $this->assertNotContains(PermissionName::DRIVER_JOURNEYS_START->value, $permissions);
        $this->assertNotContains(PermissionName::DRIVER_JOURNEYS_FINISH->value, $permissions);
        $this->assertContains(PermissionName::DRIVER_DELIVERIES_VIEW->value, $permissions);
        $this->assertNotContains(PermissionName::DRIVER_DELIVERIES_SUBMIT_OUTCOME->value, $permissions);
        $this->assertContains(PermissionName::CUSTOMERS_VIEW->value, $permissions);

        $salesPermissions = RolePermissionMap::all()['sales_representative'];
        $this->assertContains(PermissionName::DRIVER_JOURNEYS_VIEW->value, $salesPermissions);
        $this->assertContains(PermissionName::DRIVER_DELIVERIES_VIEW->value, $salesPermissions);
    }
}
