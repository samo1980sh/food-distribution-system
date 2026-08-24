<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionName;
use App\Support\Api\MobileSyncEntityRegistry;
use App\Support\Api\MobileSyncPushRegistry;
use App\Support\Authorization\RolePermissionMap;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesFieldOperationsContractTest extends TestCase
{
    #[Test]
    public function sales_field_entities_and_customer_create_are_exposed_for_sync(): void
    {
        $this->assertSame(8, MobileSyncEntityRegistry::VERSION);
        $this->assertArrayHasKey('sales_journeys', MobileSyncEntityRegistry::definitions());
        $this->assertArrayHasKey('sales_visits', MobileSyncEntityRegistry::definitions());
        $this->assertTrue(MobileSyncPushRegistry::supports('customers', 'create'));
        $this->assertTrue(MobileSyncPushRegistry::supports('sales_journeys', 'start'));
        $this->assertTrue(MobileSyncPushRegistry::supports('sales_journeys', 'finish'));
        $this->assertTrue(MobileSyncPushRegistry::supports('sales_visits', 'start'));
        $this->assertTrue(MobileSyncPushRegistry::supports('sales_visits', 'complete'));
    }

    #[Test]
    public function sales_representative_has_only_the_required_field_mutations(): void
    {
        $permissions = RolePermissionMap::all()['sales_representative'];

        foreach ([
            PermissionName::CUSTOMERS_CREATE,
            PermissionName::SALES_JOURNEYS_VIEW,
            PermissionName::SALES_JOURNEYS_OPEN,
            PermissionName::SALES_JOURNEYS_START,
            PermissionName::SALES_JOURNEYS_FINISH,
            PermissionName::SALES_VISITS_VIEW,
            PermissionName::SALES_VISITS_START,
            PermissionName::SALES_VISITS_COMPLETE,
        ] as $permission) {
            $this->assertContains($permission->value, $permissions);
        }
    }

    #[Test]
    public function approved_flutter_ui_is_outside_the_laravel_contract_change(): void
    {
        $documentation = file_get_contents(
            base_path('docs/api/MOBILE_SALES_FIELD_OPERATIONS_PHASE3F_AR.md'),
        );

        $this->assertIsString($documentation);
        $this->assertStringContainsString('لا تعيد تصميم', $documentation);
        $this->assertStringContainsString('الانطلاق', $documentation);
        $this->assertStringContainsString('الإقفال', $documentation);
    }
}
