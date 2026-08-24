<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\VehicleLoadItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedRepresentativeJourneyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_runs_the_unified_field_day_without_driver_journey(): void
    {
        $context = $this->context('PRIMARY', 1);
        $user = $this->representativeUser($context['representative']);
        $token = $this->tokenFor($user);
        $load = $this->pendingLoad($context);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.route.id', $context['route']->id)
            ->assertJsonPath('data.vehicle.id', $context['vehicle']->id)
            ->assertJsonPath('data.warehouse.id', $context['warehouse']->id);

        $journeyId = (int) $opened->json('data.id');
        $visitId = (int) $opened->json('data.visits.0.id');

        $this->assertDatabaseCount('driver_journeys', 0);

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start")
            ->assertConflict()
            ->assertJsonPath('code', 'vehicle_load_handover_pending');

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/vehicle-loads/'.$load->id.'/acknowledge', [
                'handover_status' => 'received',
                'items' => [[
                    'id' => $load->items->first()->id,
                    'received_quantity' => '10.000',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.handover_status', 'received');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $closing = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/daily-closings/open-today', [
                'route_id' => $context['route']->id,
            ])
            ->assertCreated();
        $closingId = (int) $closing->json('data.id');
        $expectedQuantity = (float) $closing->json('data.items.0.expected_quantity');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/daily-closings/{$closingId}/submit-inventory", [
                'items' => [[
                    'product_id' => $context['product']->id,
                    'actual_quantity' => $expectedQuantity,
                ]],
            ])
            ->assertConflict();

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/vehicle-expenses', [
                'client_reference' => 'unified-journey-expense',
                'expense_date' => today()->toDateString(),
                'route_id' => $context['route']->id,
                'vehicle_id' => $context['vehicle']->id,
                'warehouse_id' => $context['warehouse']->id,
                'expense_type' => 'fuel',
                'amount' => 15,
                'payment_method' => 'cash',
            ])
            ->assertCreated()
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.operation_source', 'mobile_sales');

        $this->withFreshToken($token)
            ->getJson('/api/v1/operational/today')
            ->assertOk()
            ->assertJsonPath('data.contexts.sales_representative.readiness.ready', true)
            ->assertJsonPath('data.contexts.sales_representative.summary.journey.id', $journeyId)
            ->assertJsonPath('data.contexts.sales_representative.summary.journey.status', 'in_progress')
            ->assertJsonPath('data.contexts.sales_representative.summary.load_custody.status', 'received')
            ->assertJsonPath('data.contexts.sales_representative.summary.stock.total_quantity', '20.000')
            ->assertJsonPath('data.contexts.sales_representative.summary.expenses.total', 1);

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/start")
            ->assertOk();
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/complete", [
                'outcome' => 'no_order',
            ])
            ->assertOk();
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/finish")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/daily-closings/{$closingId}/submit-inventory", [
                'items' => [[
                    'product_id' => $context['product']->id,
                    'actual_quantity' => $expectedQuantity,
                ]],
            ])
            ->assertOk();
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/daily-closings/{$closingId}/submit-cash", [
                'actual_cash_amount' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.field_handover.complete', true);

        $this->assertDatabaseHas('daily_closings', [
            'id' => $closingId,
            'driver_id' => null,
            'inventory_submitted_by' => $user->id,
            'cash_submitted_by' => $user->id,
        ]);
        $this->assertDatabaseCount('driver_journeys', 0);
        $this->assertDatabaseCount('driver_deliveries', 0);
    }

    public function test_representative_cannot_start_a_conflicting_or_unauthorized_journey(): void
    {
        $first = $this->context('FIRST', 0);
        $second = $this->context('SECOND', 0);
        $unauthorized = $this->context('UNAUTHORIZED', 0);
        $second['route']->update([
            'sales_representative_id' => $first['representative']->id,
        ]);
        $user = $this->representativeUser($first['representative']);
        $token = $this->tokenFor($user);

        $firstJourney = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today', [
                'route_id' => $first['route']->id,
            ])
            ->assertCreated();
        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/'.$firstJourney->json('data.id').'/start')
            ->assertOk();

        $secondJourney = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today', [
                'route_id' => $second['route']->id,
            ])
            ->assertCreated();
        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/'.$secondJourney->json('data.id').'/start')
            ->assertConflict()
            ->assertJsonPath('code', 'sales_journey_conflict');

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today', [
                'route_id' => $unauthorized['route']->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('driver_journeys', 0);
    }

    /** @return array<string, mixed> */
    private function context(string $suffix, int $customerCount): array
    {
        $area = Area::query()->create([
            'code' => 'UNIFIED-AREA-'.$suffix,
            'name_ar' => 'Area '.$suffix,
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'UNIFIED-VEH-'.$suffix,
            'plate_number' => 'UNIFIED-PLATE-'.$suffix,
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'UNIFIED-WH-'.$suffix,
            'name' => 'Vehicle warehouse '.$suffix,
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'UNIFIED-SOURCE-'.$suffix,
            'name' => 'Source warehouse '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'UNIFIED-REP-'.$suffix,
            'name' => 'Representative '.$suffix,
            'type' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => null,
            'sales_representative_id' => $representative->id,
            'code' => 'UNIFIED-ROUTE-'.$suffix,
            'name' => 'Route '.$suffix,
            'visit_days' => [],
            'status' => 'active',
        ]);

        for ($index = 1; $index <= $customerCount; $index++) {
            Customer::query()->create([
                'code' => "UNIFIED-CUSTOMER-{$suffix}-{$index}",
                'name' => "Customer {$suffix} {$index}",
                'area_id' => $area->id,
                'route_id' => $route->id,
                'status' => 'active',
            ]);
        }

        $category = ProductCategory::query()->create([
            'code' => 'UNIFIED-CAT-'.$suffix,
            'name_ar' => 'Category '.$suffix,
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'UNIFIED-UNIT-'.$suffix,
            'name_ar' => 'Unit '.$suffix,
            'symbol' => 'U',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'UNIFIED-SKU-'.$suffix,
            'name_ar' => 'Product '.$suffix,
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

        return compact(
            'area',
            'vehicle',
            'warehouse',
            'sourceWarehouse',
            'representative',
            'route',
            'product',
        );
    }

    /** @param array<string, mixed> $context */
    private function pendingLoad(array $context): VehicleLoad
    {
        $load = VehicleLoad::query()->create([
            'load_number' => 'UNIFIED-LOAD',
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'driver_id' => null,
            'sales_representative_id' => $context['representative']->id,
            'from_warehouse_id' => $context['sourceWarehouse']->id,
            'to_warehouse_id' => $context['warehouse']->id,
            'load_date' => today(),
            'status' => 'approved',
            'handover_status' => 'pending',
            'total_quantity' => 10,
        ]);
        VehicleLoadItem::query()->create([
            'vehicle_load_id' => $load->id,
            'product_id' => $context['product']->id,
            'quantity' => 10,
            'unit_cost' => 5,
            'total_cost' => 50,
        ]);

        return $load->load('items');
    }

    private function representativeUser(Employee $representative): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => User::STATUS_ACTIVE,
        ]);
        $representative->update(['user_id' => $user->id]);

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken(
            'unified-representative-journey-test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;
    }

    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
