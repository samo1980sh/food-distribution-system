<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\VehicleLoadItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FieldTodayReadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-27 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_field_today_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/operational/today')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_sales_representative_receives_scoped_today_context_and_summary(): void
    {
        $first = $this->context('A', ['monday']);
        $second = $this->context('B', ['monday']);
        $first['route']->update(['driver_id' => null]);
        $user = $this->userForEmployee(User::ROLE_SALES_REPRESENTATIVE, $first['representative']);

        $this->invoice($first, 'A-DRAFT', 'draft', 25);
        $this->invoice($first, 'A-CONFIRMED', 'confirmed', 125);
        $this->invoice($second, 'B-CONFIRMED', 'confirmed', 900);
        $this->payment($first, 'A-PAYMENT', 'confirmed', 50);
        $this->salesReturn($first, 'A-RETURN', 'confirmed', 15);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/operational/today')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-07-27')
            ->assertJsonPath('data.available_roles.0', User::ROLE_SALES_REPRESENTATIVE)
            ->assertJsonPath('data.contexts.driver', null)
            ->assertJsonPath('data.contexts.sales_representative.status', 'ready')
            ->assertJsonPath('data.contexts.sales_representative.readiness.ready', true)
            ->assertJsonPath('data.contexts.sales_representative.route.id', $first['route']->id)
            ->assertJsonPath('data.contexts.sales_representative.summary.assigned_customers', 1)
            ->assertJsonPath('data.contexts.sales_representative.summary.invoices.total', 2)
            ->assertJsonPath('data.contexts.sales_representative.summary.invoices.confirmed', 1)
            ->assertJsonPath('data.contexts.sales_representative.summary.invoices.confirmed_amount', '125.00')
            ->assertJsonPath('data.contexts.sales_representative.summary.payments.confirmed_amount', '50.00')
            ->assertJsonPath('data.contexts.sales_representative.summary.returns.confirmed_amount', '15.00');
    }

    public function test_unified_representative_workspace_takes_priority_for_dual_role_user(): void
    {
        $context = $this->context('DUAL', ['monday']);
        $user = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $user->syncRoles([
            User::ROLE_DRIVER,
            User::ROLE_SALES_REPRESENTATIVE,
        ]);
        $context['driver']->update([
            'user_id' => $user->id,
            'type' => User::ROLE_SALES_REPRESENTATIVE,
        ]);
        $context['route']->update([
            'driver_id' => $context['driver']->id,
            'sales_representative_id' => $context['driver']->id,
        ]);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/operational/today')
            ->assertOk()
            ->assertJsonCount(1, 'data.available_roles')
            ->assertJsonPath('data.available_roles.0', User::ROLE_SALES_REPRESENTATIVE)
            ->assertJsonPath('data.contexts.driver', null)
            ->assertJsonPath('data.contexts.sales_representative.route.id', $context['route']->id)
            ->assertJsonPath(
                'data.contexts.sales_representative.route.driver.assignment_role',
                User::ROLE_DRIVER,
            )
            ->assertJsonPath(
                'data.contexts.sales_representative.route.sales_representative.assignment_role',
                User::ROLE_SALES_REPRESENTATIVE,
            );
    }

    private function context(string $suffix, array $visitDays): array
    {
        $area = Area::query()->create([
            'code' => 'TODAY-AREA-'.$suffix,
            'name_ar' => 'منطقة '.$suffix,
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'TODAY-VEH-'.$suffix,
            'plate_number' => 'TODAY-PLATE-'.$suffix,
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'TODAY-WH-'.$suffix,
            'name' => 'مستودع سيارة '.$suffix,
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'TODAY-SOURCE-'.$suffix,
            'name' => 'مستودع مصدر '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'TODAY-DRV-'.$suffix,
            'name' => 'سائق '.$suffix,
            'type' => 'driver',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'TODAY-REP-'.$suffix,
            'name' => 'مندوب '.$suffix,
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'TODAY-ROUTE-'.$suffix,
            'name' => 'خط '.$suffix,
            'visit_days' => $visitDays,
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'code' => 'TODAY-CUS-'.$suffix,
            'name' => 'عميل '.$suffix,
            'area_id' => $area->id,
            'route_id' => $route->id,
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'TODAY-CAT-'.$suffix,
            'name_ar' => 'تصنيف '.$suffix,
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'TODAY-UNIT-'.$suffix,
            'name_ar' => 'وحدة '.$suffix,
            'symbol' => 'U',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'TODAY-SKU-'.$suffix,
            'name_ar' => 'منتج '.$suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 5,
            'sale_price' => 10,
            'wholesale_price' => 9,
            'status' => 'active',
        ]);
        StockBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'average_unit_cost' => 5,
        ]);

        return [
            'area' => $area,
            'vehicle' => $vehicle,
            'warehouse' => $warehouse,
            'source_warehouse' => $sourceWarehouse,
            'driver' => $driver,
            'representative' => $representative,
            'route' => $route,
            'customer' => $customer,
            'product' => $product,
        ];
    }

    /** @param array<string, mixed> $context */
    private function invoice(array $context, string $suffix, string $status, float $amount): SalesInvoice
    {
        return SalesInvoice::withoutEvents(fn () => SalesInvoice::query()->create([
            'invoice_number' => 'TODAY-INV-'.$suffix,
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'invoice_date' => today(),
            'status' => $status,
            'payment_type' => 'cash',
            'total_amount' => $amount,
        ]));
    }

    /** @param array<string, mixed> $context */
    private function payment(array $context, string $suffix, string $status, float $amount): CustomerPayment
    {
        return CustomerPayment::withoutEvents(fn () => CustomerPayment::query()->create([
            'payment_number' => 'TODAY-PAY-'.$suffix,
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'payment_date' => today(),
            'payment_method' => 'cash',
            'status' => $status,
            'amount' => $amount,
        ]));
    }

    /** @param array<string, mixed> $context */
    private function salesReturn(array $context, string $suffix, string $status, float $amount): SalesReturn
    {
        return SalesReturn::withoutEvents(fn () => SalesReturn::query()->create([
            'return_number' => 'TODAY-RET-'.$suffix,
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'return_date' => today(),
            'status' => $status,
            'total_amount' => $amount,
        ]));
    }

    private function userForEmployee(string $role, Employee $employee): User
    {
        $user = User::factory()->create(['role' => $role]);
        $employee->update(['user_id' => $user->id]);

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken(
            'field-today-test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;
    }

    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
