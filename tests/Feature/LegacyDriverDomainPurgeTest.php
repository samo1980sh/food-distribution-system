<?php

namespace Tests\Feature;

use App\Enums\EmployeeType;
use App\Enums\OperationSource;
use App\Enums\PermissionName;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Rules\AllowedUserRoleCombination;
use App\Services\Distribution\SalesFieldOperationService;
use App\Support\Api\MobileAppAccess;
use App\Support\Api\MobileSyncEntityRegistry;
use App\Support\Api\MobileSyncPushRegistry;
use App\Support\Authorization\RolePermissionMap;
use App\Support\Filament\OperationalFormContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyDriverDomainPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_schema_has_no_legacy_driver_domain(): void
    {
        foreach (['driver_journeys', 'driver_deliveries', 'driver_delivery_items'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }

        foreach (['distribution_routes', 'vehicle_loads', 'vehicle_expenses', 'sales_journeys', 'daily_closings'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'driver_id'));
        }
    }

    public function test_current_identity_and_authorization_contract_has_no_driver_role(): void
    {
        $this->assertNotContains('driver', array_column(UserRole::cases(), 'value'));
        $this->assertNotContains('mobile_driver', array_column(OperationSource::cases(), 'value'));
        $permissions = array_column(PermissionName::cases(), 'value');
        $this->assertNotContains('driver_journeys.view', $permissions);
        $this->assertNotContains('driver_deliveries.view', $permissions);
        $this->assertArrayNotHasKey('driver', RolePermissionMap::all());

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseMissing('roles', ['name' => 'driver']);
        $this->assertDatabaseHas('roles', [
            'name' => UserRole::SALES_REPRESENTATIVE->value,
            'guard_name' => 'web',
        ]);

        $representativeRole = Role::findByName(UserRole::SALES_REPRESENTATIVE->value, 'web');
        $this->assertTrue($this->roleSelectionPasses($representativeRole->id));

        $forgedDriverRole = Role::findOrCreate('driver', 'web');
        $this->assertFalse($this->roleSelectionPasses($forgedDriverRole->id));

        $this->assertFileDoesNotExist(app_path('Models/DriverJourney.php'));
        $this->assertFileDoesNotExist(app_path('Models/DriverDelivery.php'));
        $this->assertFileDoesNotExist(app_path('Models/DriverDeliveryItem.php'));
        $this->assertFileDoesNotExist(app_path('Policies/DriverJourneyPolicy.php'));
        $this->assertFileDoesNotExist(app_path('Policies/DriverDeliveryPolicy.php'));
    }

    public function test_employee_type_is_a_central_closed_contract(): void
    {
        $this->assertSame([
            'sales_representative',
            'warehouse_keeper',
            'accountant',
            'supervisor',
        ], EmployeeType::values());

        foreach (EmployeeType::cases() as $index => $employeeType) {
            Employee::query()->create([
                'employee_code' => 'PURGE-VALID-'.($index + 1),
                'name' => 'Valid employee '.($index + 1),
                'type' => $employeeType->value,
                'status' => 'active',
            ]);
        }

        $this->assertSame(count(EmployeeType::cases()), Employee::query()->count());
        $this->assertEmployeeTypeRejected('driver', 'PURGE-DRIVER');
        $this->assertEmployeeTypeRejected('unsupported_type', 'PURGE-UNKNOWN');

        $employee = Employee::query()->where('employee_code', 'PURGE-VALID-1')->firstOrFail();

        try {
            $employee->update(['type' => 'driver']);
            $this->fail('A current employee must not be convertible to the retired driver type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }

        $this->assertSame(
            EmployeeType::SALES_REPRESENTATIVE->value,
            $employee->refresh()->type,
        );
    }

    public function test_sync_bootstrap_and_routes_expose_only_the_unified_representative_runtime(): void
    {
        foreach (['driver_journeys', 'driver_deliveries'] as $entity) {
            $this->assertArrayNotHasKey($entity, MobileSyncEntityRegistry::definitions());
            $this->assertArrayNotHasKey($entity, MobileSyncPushRegistry::definitions());
        }

        $this->assertSame(
            [UserRole::SALES_REPRESENTATIVE->value],
            array_values(config('mobile_api.allowed_roles')),
        );
        $this->assertTrue(Route::has('api.v1.operational.bootstrap'));
        $this->assertTrue(Route::has('api.v1.operational.sales-journeys.open-today'));
        $this->assertTrue(Route::has('api.v1.operational.sales-visits.start'));
    }

    public function test_unified_representative_journey_can_be_opened(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $area = Area::query()->create([
            'code' => 'PURGE-AREA',
            'name_ar' => 'Purge area',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'PURGE-VEHICLE',
            'plate_number' => 'PURGE-PLATE',
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'PURGE-WAREHOUSE',
            'name' => 'Purge vehicle warehouse',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'PURGE-REPRESENTATIVE',
            'name' => 'Purge representative',
            'type' => EmployeeType::SALES_REPRESENTATIVE->value,
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'sales_representative_id' => $representative->id,
            'code' => 'PURGE-ROUTE',
            'name' => 'Purge route',
            'visit_days' => [],
            'status' => 'active',
        ]);
        Customer::query()->create([
            'code' => 'PURGE-CUSTOMER',
            'name' => 'Purge customer',
            'area_id' => $area->id,
            'route_id' => $route->id,
            'status' => 'active',
        ]);

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->syncRoles([UserRole::SALES_REPRESENTATIVE->value]);
        $representative->update(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->assertTrue(MobileAppAccess::allowsLogin($user));
        $this->assertSame([
            'role' => UserRole::SALES_REPRESENTATIVE->value,
            'unified' => true,
            'legacy' => false,
        ], MobileAppAccess::fieldWorkspace($user));
        $this->assertSame([
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'sales_representative_id' => $representative->id,
        ], OperationalFormContext::forRepresentativeRoute($route->id));

        $journey = app(SalesFieldOperationService::class)->openToday($user, $route->id);

        $this->assertTrue($journey->wasRecentlyCreated);
        $this->assertSame('ready', $journey->status);
        $this->assertSame($representative->id, $journey->sales_representative_id);
        $this->assertSame($warehouse->id, $journey->warehouse_id);
        $this->assertCount(1, $journey->visits);
    }

    public function test_no_legacy_driver_api_routes_are_registered(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('driver-journey', $route->uri());
            $this->assertStringNotContainsString('driver-deliver', $route->uri());
        }
    }

    private function roleSelectionPasses(int $roleId): bool
    {
        return Validator::make(
            ['roles' => [$roleId]],
            ['roles' => ['required', new AllowedUserRoleCombination]],
        )->passes();
    }

    private function assertEmployeeTypeRejected(string $type, string $employeeCode): void
    {
        try {
            Employee::query()->create([
                'user_id' => null,
                'employee_code' => $employeeCode,
                'name' => 'Rejected employee',
                'type' => $type,
                'status' => 'active',
            ]);
            $this->fail("Employee type [{$type}] must be rejected.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }

        $this->assertDatabaseMissing('employees', ['employee_code' => $employeeCode]);
    }
}
