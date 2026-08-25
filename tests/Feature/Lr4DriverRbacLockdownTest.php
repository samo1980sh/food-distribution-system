<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use App\Support\Authorization\RolePermissionMap;
use Tests\TestCase;

class Lr4DriverRbacLockdownTest extends TestCase
{
    public function test_driver_role_is_retained_for_read_only_compatibility_without_mobile_or_field_mutations(): void
    {
        $permissions = RolePermissionMap::all()[UserRole::DRIVER->value];

        $this->assertContains(
            PermissionName::DRIVER_JOURNEYS_VIEW->value,
            $permissions,
        );

        $this->assertContains(
            PermissionName::DRIVER_DELIVERIES_VIEW->value,
            $permissions,
        );

        $this->assertContains(
            PermissionName::VEHICLE_LOADS_VIEW->value,
            $permissions,
        );

        $this->assertContains(
            PermissionName::DAILY_CLOSINGS_VIEW->value,
            $permissions,
        );

        $this->assertNotContains(
            PermissionName::API_ACCESS->value,
            $permissions,
        );

        $this->assertNotContains(
            PermissionName::VEHICLE_EXPENSES_CREATE->value,
            $permissions,
        );

        $this->assertNotContains(
            PermissionName::VEHICLE_EXPENSES_UPDATE->value,
            $permissions,
        );

        $this->assertNotContains(
            PermissionName::DAILY_CLOSINGS_OPEN_FIELD->value,
            $permissions,
        );

        $this->assertNotContains(
            PermissionName::DAILY_CLOSINGS_SUBMIT_INVENTORY->value,
            $permissions,
        );
    }

    public function test_no_role_receives_retired_driver_runtime_mutation_permissions(): void
    {
        $retiredPermissions = [
            PermissionName::DRIVER_JOURNEYS_OPEN->value,
            PermissionName::DRIVER_JOURNEYS_START->value,
            PermissionName::DRIVER_JOURNEYS_FINISH->value,
            PermissionName::DRIVER_DELIVERIES_SUBMIT_OUTCOME->value,
        ];

        foreach (RolePermissionMap::all() as $role => $permissions) {
            foreach ($retiredPermissions as $permission) {
                $this->assertNotContains(
                    $permission,
                    $permissions,
                    "Role [{$role}] must not receive retired driver permission [{$permission}].",
                );
            }
        }
    }

    public function test_historical_driver_view_permissions_remain_available_to_relevant_roles(): void
    {
        $map = RolePermissionMap::all();

        foreach ([
            UserRole::SUPER_ADMIN->value,
            UserRole::MANAGER->value,
            UserRole::SUPERVISOR->value,
            UserRole::SALES_REPRESENTATIVE->value,
            UserRole::DRIVER->value,
        ] as $role) {
            $this->assertContains(
                PermissionName::DRIVER_JOURNEYS_VIEW->value,
                $map[$role],
            );

            $this->assertContains(
                PermissionName::DRIVER_DELIVERIES_VIEW->value,
                $map[$role],
            );
        }
    }
}
