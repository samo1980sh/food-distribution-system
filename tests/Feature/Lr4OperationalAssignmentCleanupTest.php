<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Support\Filament\AdminOperationalDriverGuard;
use App\Support\Filament\OperationalFormContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Lr4OperationalAssignmentCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_guard_blocks_new_and_reintroduced_driver_assignments(): void
    {
        $this->assertNull(AdminOperationalDriverGuard::sanitize(['driver_id' => 41])['driver_id']);

        foreach ([new DistributionRoute, new VehicleLoad, new VehicleExpense] as $record) {
            $record->setAttribute('driver_id', null);

            $this->assertNull(AdminOperationalDriverGuard::sanitize(
                ['driver_id' => 42],
                $record,
            )['driver_id']);
        }
    }

    public function test_admin_guard_preserves_existing_historical_driver_on_unrelated_edit(): void
    {
        foreach ([new DistributionRoute, new VehicleLoad, new VehicleExpense] as $record) {
            $record->setAttribute('driver_id', 51);

            $sanitized = AdminOperationalDriverGuard::sanitize([
                'driver_id' => 99,
                'notes' => 'updated',
            ], $record);

            $this->assertSame(51, $sanitized['driver_id']);
            $this->assertSame('updated', $sanitized['notes']);
        }
    }

    public function test_representative_route_context_excludes_driver_but_legacy_context_remains_available(): void
    {
        $area = Area::query()->create([
            'code' => 'LR4-P3-AREA',
            'name_ar' => 'منطقة المرحلة الثالثة',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'LR4-P3-VEHICLE',
            'plate_number' => 'LR4-P3-PLATE',
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'LR4-P3-WAREHOUSE',
            'name' => 'مستودع سيارة المرحلة الثالثة',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'LR4-P3-DRIVER',
            'name' => 'سائق تاريخي',
            'type' => 'driver',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'LR4-P3-REP',
            'name' => 'مندوب المرحلة الثالثة',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'LR4-P3-ROUTE',
            'name' => 'خط المرحلة الثالثة',
            'status' => 'active',
        ]);

        $activeContext = OperationalFormContext::forRepresentativeRoute($route->id);
        $legacyContext = OperationalFormContext::forRoute($route->id);

        $this->assertSame($vehicle->id, $activeContext['vehicle_id']);
        $this->assertSame($warehouse->id, $activeContext['warehouse_id']);
        $this->assertSame($representative->id, $activeContext['sales_representative_id']);
        $this->assertArrayNotHasKey('driver_id', $activeContext);
        $this->assertSame($driver->id, $legacyContext['driver_id']);
    }

    public function test_active_admin_forms_do_not_offer_or_derive_driver_assignment(): void
    {
        $routeForm = file_get_contents(app_path('Filament/Resources/DistributionRoutes/Schemas/DistributionRouteForm.php'));
        $loadForm = file_get_contents(app_path('Filament/Resources/VehicleLoads/Schemas/VehicleLoadForm.php'));
        $expenseForm = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Schemas/VehicleExpenseForm.php'));

        foreach ([$routeForm, $loadForm, $expenseForm] as $form) {
            $this->assertIsString($form);
            $this->assertStringNotContainsString("Select::make('driver_id')", $form);
            $this->assertStringContainsString("Select::make('sales_representative_id')", $form);
        }

        foreach ([$loadForm, $expenseForm] as $form) {
            $this->assertStringNotContainsString("\$set('driver_id'", $form);
            $this->assertStringContainsString('OperationalFormContext::forRepresentativeRoute', $form);
        }
    }

    public function test_scoped_filament_writes_use_the_server_side_driver_guard(): void
    {
        $files = [
            'Filament/Resources/DistributionRoutes/Pages/ManageDistributionRoutes.php',
            'Filament/Resources/DistributionRoutes/Tables/DistributionRoutesTable.php',
            'Filament/Resources/VehicleLoads/Pages/ListVehicleLoads.php',
            'Filament/Resources/VehicleLoads/Pages/ManageVehicleLoads.php',
            'Filament/Resources/VehicleLoads/Pages/CreateVehicleLoad.php',
            'Filament/Resources/VehicleLoads/Pages/EditVehicleLoad.php',
            'Filament/Resources/VehicleLoads/Pages/ViewVehicleLoad.php',
            'Filament/Resources/VehicleLoads/Tables/VehicleLoadsTable.php',
            'Filament/Resources/VehicleExpenses/Pages/ManageVehicleExpenses.php',
            'Filament/Resources/VehicleExpenses/Pages/ViewVehicleExpense.php',
            'Filament/Resources/VehicleExpenses/Tables/VehicleExpensesTable.php',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents(app_path($file));

            $this->assertIsString($contents);
            $this->assertStringContainsString('AdminOperationalDriverGuard::sanitize', $contents, $file);
        }
    }

    public function test_historical_driver_display_filter_and_relationships_remain_available(): void
    {
        $routeTable = file_get_contents(app_path('Filament/Resources/DistributionRoutes/Tables/DistributionRoutesTable.php'));
        $loadTable = file_get_contents(app_path('Filament/Resources/VehicleLoads/Tables/VehicleLoadsTable.php'));
        $expenseTable = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Tables/VehicleExpensesTable.php'));

        $this->assertStringContainsString("TextColumn::make('driver.name')", $routeTable);
        $this->assertStringContainsString("SelectFilter::make('driver_id')", $routeTable);
        $this->assertStringContainsString("TextColumn::make('driver.name')", $loadTable);
        $this->assertStringContainsString("TextColumn::make('driver.name')", $expenseTable);
        $this->assertTrue(method_exists(new DistributionRoute, 'driver'));
        $this->assertTrue(method_exists(new VehicleLoad, 'driver'));
        $this->assertTrue(method_exists(new VehicleExpense, 'driver'));
    }
}
