<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyClosingFieldHandoverApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_closing_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/operational/daily-closings/open-today')
            ->assertUnauthorized();
    }

    public function test_sales_representative_opens_closing_with_both_handover_capabilities_without_driver(): void
    {
        $context = $this->context();
        $context['route']->update(['driver_id' => null]);
        $sales = $this->userForEmployee(User::ROLE_SALES_REPRESENTATIVE, $context['representative']);

        $salesToken = $this->tokenFor($sales);

        $this->withFreshToken($salesToken)
            ->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.modules.daily_closings', true)
            ->assertJsonPath('data.write.daily_closings.open_today', true)
            ->assertJsonPath('data.write.daily_closings.submit_inventory', true)
            ->assertJsonPath('data.write.daily_closings.submit_cash', true)
            ->assertJsonPath('data.write.daily_closings.create', false);

        $created = $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/open-today', [
                'route_id' => $context['route']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.field_workflow', true)
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.sales_representative.id', $context['representative']->id)
            ->assertJsonPath('data.actions.can_submit_inventory', true)
            ->assertJsonPath('data.actions.can_submit_cash', true)
            ->assertJsonPath('data.field_handover.inventory.submitted', false);

        $this->assertSame('0.00', $created->json('data.financial.expected_cash_amount'));
        $this->assertMatchesRegularExpression(
            '/^c:[1-9][0-9]*$/',
            (string) $created->json('data.sync_version'),
        );

        $closingId = (int) $created->json('data.id');

        $this->assertDatabaseCount('daily_closings', 1);
        $this->assertDatabaseHas('daily_closings', [
            'id' => $closingId,
            'field_workflow' => true,
            'driver_id' => null,
            'sales_representative_id' => $context['representative']->id,
        ]);

        $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings', [
                'client_reference' => 'forbidden-general-create',
            ])
            ->assertForbidden();
    }

    public function test_representative_submits_both_sections_and_differences_require_notes(): void
    {
        $context = $this->context();
        $context['route']->update(['driver_id' => null]);
        $sales = $this->userForEmployee(User::ROLE_SALES_REPRESENTATIVE, $context['representative']);
        $salesToken = $this->tokenFor($sales);

        $opened = $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/open-today', [
                'route_id' => $context['route']->id,
            ])
            ->assertCreated();

        $closingId = (int) $opened->json('data.id');
        $expected = (float) $opened->json('data.items.0.expected_quantity');

        $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/'.$closingId.'/submit-inventory', [
                'items' => [[
                    'product_id' => $context['product']->id,
                    'actual_quantity' => $expected - 1,
                ]],
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'business_rule_violation');

        $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/'.$closingId.'/submit-inventory', [
                'items' => [[
                    'product_id' => $context['product']->id,
                    'actual_quantity' => $expected - 1,
                    'notes' => 'عبوة تالفة أثناء التوزيع',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.field_handover.inventory.submitted', true)
            ->assertJsonPath('data.field_handover.complete', false)
            ->assertJsonPath('data.actions.can_submit_inventory', false)
            ->assertJsonStructure(['data' => ['sync_version']]);

        $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/'.$closingId.'/submit-inventory', [
                'items' => [[
                    'product_id' => $context['product']->id,
                    'actual_quantity' => $expected,
                ]],
            ])
            ->assertForbidden();

        $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/'.$closingId.'/submit-cash', [
                'actual_cash_amount' => 10,
            ])
            ->assertConflict();

        $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/'.$closingId.'/submit-cash', [
                'actual_cash_amount' => 10,
                'cash_notes' => 'زيادة نقدية قيد المراجعة',
            ])
            ->assertOk()
            ->assertJsonPath('data.field_handover.cash.submitted', true)
            ->assertJsonPath('data.field_handover.complete', true)
            ->assertJsonPath('data.actions.can_submit_cash', false)
            ->assertJsonPath('data.cash_difference', '10.00')
            ->assertJsonStructure(['data' => ['sync_version']]);

        $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/'.$closingId.'/submit-cash', [
                'actual_cash_amount' => 0,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('daily_closings', [
            'id' => $closingId,
            'inventory_submitted_by' => $sales->id,
            'cash_submitted_by' => $sales->id,
            'cash_notes' => 'زيادة نقدية قيد المراجعة',
            'status' => 'draft',
        ]);
    }

    public function test_representative_cannot_open_closing_for_another_route_and_vehicle(): void
    {
        $context = $this->context();
        $sales = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );
        $otherArea = Area::query()->create([
            'code' => 'FIELD-CLOSE-OTHER-AREA',
            'name_ar' => 'منطقة إغلاق أخرى',
            'status' => 'active',
        ]);
        $otherVehicle = Vehicle::query()->create([
            'code' => 'FIELD-CLOSE-OTHER-VEH',
            'plate_number' => 'FIELD-CLOSE-OTHER-PLATE',
            'status' => 'active',
        ]);
        Warehouse::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'code' => 'FIELD-CLOSE-OTHER-WH',
            'name' => 'مستودع سيارة آخر',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $otherRepresentative = Employee::query()->create([
            'employee_code' => 'FIELD-CLOSE-OTHER-REP',
            'name' => 'مندوب إغلاق آخر',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $otherRoute = DistributionRoute::query()->create([
            'area_id' => $otherArea->id,
            'vehicle_id' => $otherVehicle->id,
            'driver_id' => null,
            'sales_representative_id' => $otherRepresentative->id,
            'code' => 'FIELD-CLOSE-OTHER-ROUTE',
            'name' => 'خط إغلاق آخر',
            'status' => 'active',
        ]);

        $this->withFreshToken($this->tokenFor($sales))
            ->postJson('/api/v1/operational/daily-closings/open-today', [
                'route_id' => $otherRoute->id,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'business_rule_violation');

        $this->assertDatabaseCount('daily_closings', 0);
    }

    public function test_negative_net_cash_submits_zero_without_a_false_cash_difference(): void
    {
        $context = $this->context();
        $sales = $this->userForEmployee(User::ROLE_SALES_REPRESENTATIVE, $context['representative']);
        $salesToken = $this->tokenFor($sales);

        VehicleExpense::withoutEvents(fn () => VehicleExpense::query()->create([
            'expense_number' => 'EXP-NEGATIVE-CASH',
            'expense_date' => today()->toDateString(),
            'vehicle_id' => $context['vehicle']->id,
            'warehouse_id' => $context['warehouse']->id,
            'route_id' => $context['route']->id,
            'driver_id' => null,
            'sales_representative_id' => $context['representative']->id,
            'expense_type' => 'fuel',
            'amount' => 40000,
            'payment_method' => 'cash',
            'operation_source' => 'mobile_sales',
            'status' => 'approved',
            'approved_at' => now(),
        ]));

        $opened = $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/open-today', [
                'route_id' => $context['route']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.financial.expected_cash_amount', '-40000.00');

        $closingId = (int) $opened->json('data.id');

        $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/'.$closingId.'/submit-cash', [
                'actual_cash_amount' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.actual_cash_amount', '0.00')
            ->assertJsonPath('data.cash_difference', '0.00')
            ->assertJsonPath('data.field_handover.cash.submitted', true);
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        $area = Area::query()->create([
            'code' => 'FIELD-CLOSE-AREA',
            'name_ar' => 'منطقة الإغلاق',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'FIELD-CLOSE-VEH',
            'plate_number' => 'FIELD-CLOSE-PLATE',
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'FIELD-CLOSE-WH',
            'name' => 'مستودع سيارة الإغلاق',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'FIELD-CLOSE-DRV',
            'name' => 'سائق الإغلاق',
            'type' => 'driver',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'FIELD-CLOSE-REP',
            'name' => 'مندوب الإغلاق',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'FIELD-CLOSE-ROUTE',
            'name' => 'خط الإغلاق',
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'FIELD-CLOSE-CAT',
            'name_ar' => 'تصنيف الإغلاق',
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'FIELD-CLOSE-UNIT',
            'name_ar' => 'وحدة الإغلاق',
            'symbol' => 'U',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'FIELD-CLOSE-SKU',
            'name_ar' => 'منتج الإغلاق',
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

        return compact('area', 'vehicle', 'warehouse', 'driver', 'representative', 'route', 'product');
    }

    private function userForEmployee(string $role, Employee $employee): User
    {
        $user = User::factory()->create(['role' => $role]);
        $employee->update(['user_id' => $user->id]);

        return $user;
    }

    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken(
            'daily-closing-field-test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;
    }
}
