<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdometerLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_expense_and_finish_keep_one_monotonic_vehicle_odometer(): void
    {
        $context = $this->context();
        $user = $this->salesUser($context['representative']);
        $token = $this->tokenFor($user);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated();
        $journeyId = (int) $opened->json('data.id');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_odometer']);

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start", [
                'start_odometer' => 1199,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'vehicle_odometer_regression');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start", [
                'start_odometer' => 1200,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.start_odometer', 1200)
            ->assertJsonPath('data.end_odometer', null)
            ->assertJsonPath('data.distance_km', null)
            ->assertJsonPath('data.vehicle.current_odometer', 1200);

        $this->assertDatabaseHas('sales_journeys', [
            'id' => $journeyId,
            'start_odometer' => 1200,
            'end_odometer' => null,
            'distance_km' => null,
        ]);

        $this->withFreshToken($token)
            ->getJson('/api/v1/operational/today')
            ->assertOk()
            ->assertJsonPath('data.contexts.sales_representative.summary.journey.start_odometer', 1200)
            ->assertJsonPath('data.contexts.sales_representative.summary.journey.end_odometer', null)
            ->assertJsonPath('data.contexts.sales_representative.summary.journey.distance_km', null)
            ->assertJsonPath('data.contexts.sales_representative.vehicle.current_odometer', 1200);

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/vehicle-expenses', [
                'client_reference' => 'odometer-expense-0001',
                'expense_date' => today()->toDateString(),
                'vehicle_id' => $context['vehicle']->id,
                'warehouse_id' => $context['warehouse']->id,
                'route_id' => $context['route']->id,
                'sales_representative_id' => $context['representative']->id,
                'expense_type' => 'fuel',
                'amount' => 25,
                'payment_method' => 'cash',
                'odometer_reading' => 1210,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.odometer_reading', 1210)
            ->assertJsonPath('data.vehicle.current_odometer', 1210);

        $this->assertSame(1210, (int) $context['vehicle']->refresh()->current_odometer);

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/vehicle-expenses', [
                'client_reference' => 'odometer-expense-regression',
                'expense_date' => today()->toDateString(),
                'vehicle_id' => $context['vehicle']->id,
                'warehouse_id' => $context['warehouse']->id,
                'route_id' => $context['route']->id,
                'sales_representative_id' => $context['representative']->id,
                'expense_type' => 'fuel',
                'amount' => 5,
                'payment_method' => 'cash',
                'odometer_reading' => 1209,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'vehicle_odometer_regression');

        $this->assertFalse(VehicleExpense::query()
            ->where('client_reference', 'odometer-expense-regression')
            ->exists());

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/finish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_odometer']);

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/finish", [
                'end_odometer' => 1209,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'vehicle_odometer_regression');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/finish", [
                'end_odometer' => 1234,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.start_odometer', 1200)
            ->assertJsonPath('data.end_odometer', 1234)
            ->assertJsonPath('data.distance_km', 34)
            ->assertJsonPath('data.vehicle.current_odometer', 1234);

        $this->assertDatabaseHas('sales_journeys', [
            'id' => $journeyId,
            'start_odometer' => 1200,
            'end_odometer' => 1234,
            'distance_km' => 34,
        ]);
        $this->assertSame(1234, (int) $context['vehicle']->refresh()->current_odometer);
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        $area = Area::query()->create([
            'code' => 'ODO-AREA',
            'name_ar' => 'منطقة العداد',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'ODO-VEH',
            'plate_number' => 'ODO-PLATE',
            'status' => 'active',
            'current_odometer' => 1200,
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'ODO-WH',
            'name' => 'مستودع سيارة العداد',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'ODO-SOURCE-WH',
            'name' => 'المستودع الرئيسي للعداد',
            'type' => 'main',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'ODO-REP',
            'name' => 'مندوب العداد',
            'type' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'sales_representative_id' => $representative->id,
            'code' => 'ODO-ROUTE',
            'name' => 'خط العداد',
            'visit_days' => [],
            'status' => 'active',
        ]);

        VehicleLoad::query()->create([
            'load_number' => 'ODO-LOAD-TODAY',
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'sales_representative_id' => $representative->id,
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $warehouse->id,
            'load_date' => today(),
            'status' => 'approved',
            'handover_status' => 'received',
            'total_quantity' => 0,
        ]);

        return compact('vehicle', 'warehouse', 'representative', 'route');
    }

    private function salesUser(Employee $representative): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => 'active',
        ]);
        $user->syncRoles([User::ROLE_SALES_REPRESENTATIVE]);
        $representative->update(['user_id' => $user->id]);

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken(
            'odometer-lifecycle-api-test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;
    }

    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
