<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use App\Services\Authorization\UserScopeAssignmentService;
use App\Services\Dashboard\ExecutiveDashboardService;
use App\Services\Reports\ProfitReportQuery;
use App\Services\Reports\TopCustomerReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleDataScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_queries_and_policies_are_limited_to_assigned_scope(): void
    {
        $first = $this->createOperationalContext('A');
        $second = $this->createOperationalContext('B');

        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);

        $supervisor->accessAreas()->attach($first['area']);
        $supervisor->accessWarehouses()->attach($first['warehouse']);

        $this->actingAs($supervisor);

        $this->assertSame([$first['area']->id], Area::query()->pluck('id')->all());
        $this->assertSame([$first['route']->id], DistributionRoute::query()->pluck('id')->all());
        $this->assertSame([$first['vehicle']->id], Vehicle::query()->pluck('id')->all());
        $this->assertSame([$first['warehouse']->id], Warehouse::query()->pluck('id')->all());
        $this->assertSame([$first['customer']->id], Customer::query()->pluck('id')->all());
        $this->assertSame([$first['invoice']->id], SalesInvoice::query()->pluck('id')->all());

        $this->assertTrue($supervisor->can('view', $first['invoice']));
        $this->assertFalse($supervisor->can('view', $second['invoice']));
    }


    public function test_shared_warehouse_does_not_expand_supervisor_sales_visibility(): void
    {
        $first = $this->createOperationalContext('A');
        $second = $this->createOperationalContext('B');

        SalesInvoice::withoutGlobalScopes()
            ->whereKey($second['invoice']->id)
            ->update(['warehouse_id' => $first['warehouse']->id]);

        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);
        $supervisor->accessAreas()->attach($first['area']);
        $supervisor->accessWarehouses()->attach($first['warehouse']);

        $this->actingAs($supervisor);

        $this->assertSame(
            [$first['invoice']->id],
            SalesInvoice::query()->pluck('id')->all(),
        );

        $rawIds = app(AccessScopeService::class)
            ->applyToTable(DB::table('sales_invoices'), 'sales_invoices')
            ->pluck('sales_invoices.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([$first['invoice']->id], $rawIds);
        $this->assertFalse($supervisor->can('view', $second['invoice']->fresh()));
    }

    public function test_restricted_user_without_assignments_sees_no_scoped_data(): void
    {
        $this->createOperationalContext('A');

        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);

        $this->actingAs($supervisor);

        $this->assertSame(0, Area::query()->count());
        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, SalesInvoice::query()->count());
    }

    public function test_warehouse_keeper_loads_are_limited_by_assigned_warehouses(): void
    {
        $first = $this->createOperationalContext('A');
        $second = $this->createOperationalContext('B');

        $crossLoad = VehicleLoad::query()->create([
            'load_number' => 'LOAD-CROSS',
            'vehicle_id' => $second['vehicle']->id,
            'route_id' => $second['route']->id,
            'driver_id' => $second['driver']->id,
            'sales_representative_id' => $second['representative']->id,
            'from_warehouse_id' => $first['warehouse']->id,
            'to_warehouse_id' => $second['warehouse']->id,
            'load_date' => today()->toDateString(),
            'status' => 'draft',
        ]);

        $warehouseKeeper = User::factory()->create([
            'role' => User::ROLE_WAREHOUSE_KEEPER,
        ]);
        $warehouseKeeper->accessWarehouses()->attach($first['warehouse']);

        $this->actingAs($warehouseKeeper);

        $this->assertSame([$first['warehouse']->id], Warehouse::query()->pluck('id')->all());
        $this->assertTrue(VehicleLoad::query()->whereKey($crossLoad->id)->exists());
        $this->assertFalse($warehouseKeeper->can('approve', $crossLoad));
        $this->assertFalse(VehicleLoad::query()->whereKey($second['load']->id)->exists());
    }

    public function test_sales_representative_scope_is_derived_from_linked_employee_routes(): void
    {
        $first = $this->createOperationalContext('A');
        $second = $this->createOperationalContext('B');

        $representativeUser = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
        ]);

        $first['representative']->update([
            'user_id' => $representativeUser->id,
        ]);

        $this->actingAs($representativeUser);

        $this->assertSame([$first['route']->id], DistributionRoute::query()->pluck('id')->all());
        $this->assertSame([$first['customer']->id], Customer::query()->pluck('id')->all());
        $this->assertSame([$first['invoice']->id], SalesInvoice::query()->pluck('id')->all());
        $this->assertFalse(SalesInvoice::query()->whereKey($second['invoice']->id)->exists());
    }


    public function test_driver_scope_is_derived_from_linked_employee_and_route_team(): void
    {
        $first = $this->createOperationalContext('A');
        $second = $this->createOperationalContext('B');

        $driverUser = User::factory()->create([
            'role' => User::ROLE_DRIVER,
        ]);

        $first['driver']->update([
            'user_id' => $driverUser->id,
        ]);

        $this->actingAs($driverUser);

        $this->assertSame([$first['route']->id], DistributionRoute::query()->pluck('id')->all());
        $this->assertSame([$first['vehicle']->id], Vehicle::query()->pluck('id')->all());
        $this->assertSame([$first['warehouse']->id], Warehouse::query()->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$first['driver']->id, $first['representative']->id],
            Employee::query()->pluck('id')->all(),
        );
        $this->assertTrue(VehicleLoad::query()->whereKey($first['load']->id)->exists());
        $this->assertFalse(VehicleLoad::query()->whereKey($second['load']->id)->exists());
    }

    public function test_accountant_keeps_global_financial_visibility(): void
    {
        $this->createOperationalContext('A');
        $this->createOperationalContext('B');

        $accountant = User::factory()->create([
            'role' => User::ROLE_ACCOUNTANT,
        ]);

        $this->actingAs($accountant);

        $this->assertSame(2, Customer::query()->count());
        $this->assertSame(2, SalesInvoice::query()->count());
    }

    public function test_write_guard_blocks_cross_scope_operational_payloads(): void
    {
        $first = $this->createOperationalContext('A');
        $second = $this->createOperationalContext('B');

        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);
        $supervisor->accessAreas()->attach($first['area']);
        $supervisor->accessWarehouses()->attach($first['warehouse']);

        $this->actingAs($supervisor);

        $this->expectException(AuthorizationException::class);

        SalesInvoice::query()->create([
            'invoice_number' => 'INV-BLOCKED',
            'customer_id' => $second['customer']->id,
            'vehicle_id' => $second['vehicle']->id,
            'route_id' => $second['route']->id,
            'warehouse_id' => $second['warehouse']->id,
            'sales_representative_id' => $second['representative']->id,
            'invoice_date' => today()->toDateString(),
            'status' => 'draft',
            'payment_type' => 'cash',
        ]);
    }

    public function test_scope_normalization_removes_assignments_not_supported_by_role(): void
    {
        $context = $this->createOperationalContext('A');

        $user = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);
        $user->accessAreas()->attach($context['area']);
        $user->accessVehicles()->attach($context['vehicle']);
        $user->accessWarehouses()->attach($context['warehouse']);

        $user->syncRoles([User::ROLE_WAREHOUSE_KEEPER]);
        app(UserScopeAssignmentService::class)->normalize($user);

        $this->assertFalse($user->accessAreas()->exists());
        $this->assertFalse($user->accessVehicles()->exists());
        $this->assertTrue($user->accessWarehouses()->exists());

        app(AccessScopeService::class)->forget($user);
    }


    public function test_dashboard_report_caches_and_profit_query_are_isolated_by_scope(): void
    {
        $first = $this->createOperationalContext(
            suffix: 'A',
            invoiceStatus: 'confirmed',
            invoiceAmount: 100,
        );
        $second = $this->createOperationalContext(
            suffix: 'B',
            invoiceStatus: 'confirmed',
            invoiceAmount: 250,
        );

        $firstSupervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);
        $firstSupervisor->accessAreas()->attach($first['area']);
        $firstSupervisor->accessWarehouses()->attach($first['warehouse']);

        $secondSupervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);
        $secondSupervisor->accessAreas()->attach($second['area']);
        $secondSupervisor->accessWarehouses()->attach($second['warehouse']);

        ExecutiveDashboardService::forgetCache();
        TopCustomerReportService::forgetCache();

        $this->actingAs($firstSupervisor);

        $firstSummary = app(ExecutiveDashboardService::class)->summary();
        $firstCustomerIds = app(TopCustomerReportService::class)
            ->customerIds(['limit' => 'all']);
        $firstProfitSourceIds = app(ProfitReportQuery::class)
            ->build()
            ->pluck('source_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->actingAs($secondSupervisor);

        $secondSummary = app(ExecutiveDashboardService::class)->summary();
        $secondCustomerIds = app(TopCustomerReportService::class)
            ->customerIds(['limit' => 'all']);
        $secondProfitSourceIds = app(ProfitReportQuery::class)
            ->build()
            ->pluck('source_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame(100.0, $firstSummary['today_sales']);
        $this->assertSame(250.0, $secondSummary['today_sales']);
        $this->assertSame([$first['customer']->id], $firstCustomerIds);
        $this->assertSame([$second['customer']->id], $secondCustomerIds);
        $this->assertSame([$first['invoice']->id], $firstProfitSourceIds);
        $this->assertSame([$second['invoice']->id], $secondProfitSourceIds);
    }

    public function test_route_model_binding_hides_out_of_scope_print_records(): void
    {
        $first = $this->createOperationalContext('A');
        $second = $this->createOperationalContext('B');

        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
        ]);
        $supervisor->accessAreas()->attach($first['area']);
        $supervisor->accessWarehouses()->attach($first['warehouse']);

        $this->actingAs($supervisor);

        $this->get(route('reports.sales-invoices.print', $first['invoice']))
            ->assertOk();

        $this->get(route('reports.sales-invoices.print', $second['invoice']))
            ->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function createOperationalContext(
        string $suffix,
        string $invoiceStatus = 'draft',
        float $invoiceAmount = 0,
    ): array
    {
        $area = Area::query()->create([
            'code' => 'AREA-'.$suffix,
            'name_ar' => 'منطقة '.$suffix,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::query()->create([
            'code' => 'VEH-'.$suffix,
            'plate_number' => 'PLATE-'.$suffix,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'WH-'.$suffix,
            'name' => 'مستودع '.$suffix,
            'type' => 'vehicle',
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
        ]);

        $driver = Employee::query()->create([
            'employee_code' => 'DRV-'.$suffix,
            'name' => 'سائق '.$suffix,
            'type' => 'driver',
            'status' => 'active',
        ]);

        $representative = Employee::query()->create([
            'employee_code' => 'REP-'.$suffix,
            'name' => 'مندوب '.$suffix,
            'type' => 'sales_representative',
            'status' => 'active',
        ]);

        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'ROUTE-'.$suffix,
            'name' => 'خط '.$suffix,
            'status' => 'active',
        ]);

        $customer = Customer::query()->create([
            'code' => 'CUS-'.$suffix,
            'name' => 'عميل '.$suffix,
            'area_id' => $area->id,
            'route_id' => $route->id,
            'status' => 'active',
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-'.$suffix,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'warehouse_id' => $warehouse->id,
            'sales_representative_id' => $representative->id,
            'invoice_date' => today()->toDateString(),
            'status' => $invoiceStatus,
            'payment_type' => 'cash',
            'subtotal' => $invoiceAmount,
            'total_amount' => $invoiceAmount,
            'remaining_amount' => $invoiceAmount,
            'invoice_cash_amount' => $invoiceStatus === 'confirmed'
                ? $invoiceAmount
                : 0,
        ]);

        $load = VehicleLoad::query()->create([
            'load_number' => 'LOAD-'.$suffix,
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $warehouse->id,
            'load_date' => today()->toDateString(),
            'status' => 'draft',
        ]);

        return compact(
            'area',
            'vehicle',
            'warehouse',
            'driver',
            'representative',
            'route',
            'customer',
            'invoice',
            'load',
        );
    }
}
