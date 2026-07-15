<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\VehicleLoad;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_declared_roles_and_permissions_are_seeded(): void
    {
        $this->assertSame(
            count(UserRole::cases()),
            Role::query()->where('guard_name', 'web')->count(),
        );

        $this->assertSame(
            count(PermissionName::cases()),
            Permission::query()->where('guard_name', 'web')->count(),
        );
    }

    public function test_legacy_role_attribute_assigns_spatie_role(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);

        $this->assertTrue($user->fresh()->hasRole(UserRole::SUPERVISOR->value));
        $this->assertSame(
            UserRole::SUPERVISOR->value,
            $user->fresh()->role,
        );
    }

    public function test_role_matrix_grants_only_expected_foundation_permissions(): void
    {
        $warehouseKeeper = User::factory()->create([
            'role' => User::ROLE_WAREHOUSE_KEEPER,
        ]);

        $accountant = User::factory()->create([
            'role' => User::ROLE_ACCOUNTANT,
        ]);

        $driver = User::factory()->create([
            'role' => User::ROLE_DRIVER,
        ]);

        $this->assertTrue(
            $warehouseKeeper->can(PermissionName::STOCK_MOVEMENTS_CREATE->value),
        );
        $this->assertFalse(
            $warehouseKeeper->can(PermissionName::REPORT_PROFIT->value),
        );

        $this->assertTrue(
            $accountant->can(PermissionName::CUSTOMER_PAYMENTS_CONFIRM->value),
        );
        $this->assertFalse(
            $accountant->can(PermissionName::VEHICLE_LOADS_APPROVE->value),
        );

        $this->assertFalse(
            $driver->can(PermissionName::ADMIN_ACCESS->value),
        );
        $this->assertTrue(
            $driver->can(PermissionName::VEHICLE_EXPENSES_CREATE->value),
        );
        $this->assertTrue(
            $driver->can(PermissionName::API_ACCESS->value),
        );
    }

    public function test_inactive_super_admin_is_denied_by_the_global_gate(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_INACTIVE,
        ]);

        $this->assertFalse(
            $superAdmin->can(PermissionName::USERS_VIEW->value),
        );
    }


    public function test_last_active_super_admin_cannot_be_disabled(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->expectException(ValidationException::class);

        $superAdmin->update([
            'status' => User::STATUS_INACTIVE,
        ]);
    }

    public function test_manager_cannot_update_super_admin_but_super_admin_can(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
        ]);

        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->assertFalse($manager->can('update', $superAdmin));
        $this->assertTrue($superAdmin->can('update', $manager));
    }

    public function test_operational_policy_checks_permission_and_document_state(): void
    {
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);

        $accountant = User::factory()->create([
            'role' => User::ROLE_ACCOUNTANT,
        ]);

        $draftInvoice = new SalesInvoice(['status' => 'draft']);
        $confirmedInvoice = new SalesInvoice(['status' => 'confirmed']);
        $draftLoad = new VehicleLoad(['status' => 'draft']);

        $this->assertTrue($supervisor->can('confirm', $draftInvoice));
        $this->assertFalse($supervisor->can('confirm', $confirmedInvoice));
        $this->assertFalse($accountant->can('confirm', $draftInvoice));

        $this->assertTrue($supervisor->can('approve', $draftLoad));
        $this->assertFalse($accountant->can('approve', $draftLoad));
    }

    public function test_permission_seeder_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(UserRole::cases()), Role::query()->count());
        $this->assertSame(count(PermissionName::cases()), Permission::query()->count());
    }
}
