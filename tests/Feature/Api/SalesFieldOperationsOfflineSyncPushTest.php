<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFieldOperationsOfflineSyncPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_journey_visits_and_customer_create_work_through_push_batch(): void
    {
        $context = $this->context();
        $user = $this->salesUser($context['representative']);
        $token = $this->tokenFor($user, 'sales-field-sync-device');
        $contextKey = $this->contextKey($token);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated();
        $journeyId = (int) $opened->json('data.id');

        $startOperation = [[
            'operation_id' => 'sales-start-operation',
            'entity' => 'sales_journeys',
            'action' => 'start',
            'record_id' => $journeyId,
            'base_version' => (string) $opened->json('data.sync_version'),
            'payload' => ['start_odometer' => 4000],
        ]];
        $started = $this->push($token, $contextKey, 'sales-start-batch', $startOperation)
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.record.status', 'in_progress');

        $this->push($token, $contextKey, 'sales-start-replay-batch', $startOperation)
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'replayed')
            ->assertJsonPath('data.results.0.record.status', 'in_progress');

        $customerCreate = [[
            'operation_id' => 'sales-customer-create-operation',
            'entity' => 'customers',
            'action' => 'create',
            'payload' => [
                'client_reference' => 'sales-sync-customer-0001',
                'name' => 'عميل أضيف دون اتصال',
                'route_id' => $context['route']->id,
            ],
        ]];

        $this->push($token, $contextKey, 'sales-customer-batch', $customerCreate)
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.record.route.id', $context['route']->id);

        $this->push($token, $contextKey, 'sales-customer-replay-batch', $customerCreate)
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'replayed');

        $journey = $this->withFreshToken($token)
            ->getJson("/api/v1/operational/sales-journeys/{$journeyId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.visits');

        foreach ($journey->json('data.visits') as $index => $visit) {
            $visitId = (int) $visit['id'];
            $visitResponse = $this->withFreshToken($token)
                ->getJson("/api/v1/operational/sales-visits/{$visitId}")
                ->assertOk();

            $start = $this->push($token, $contextKey, "sales-visit-start-{$index}", [[
                'operation_id' => "sales-visit-start-operation-{$index}",
                'entity' => 'sales_visits',
                'action' => 'start',
                'record_id' => $visitId,
                'base_version' => (string) $visitResponse->json('data.sync_version'),
                'payload' => [],
            ]])->assertOk()
                ->assertJsonPath('data.results.0.record.status', 'in_progress');

            $this->push($token, $contextKey, "sales-visit-complete-{$index}", [[
                'operation_id' => "sales-visit-complete-operation-{$index}",
                'entity' => 'sales_visits',
                'action' => 'complete',
                'record_id' => $visitId,
                'base_version' => (string) $start->json('data.results.0.version'),
                'payload' => ['outcome' => 'no_order'],
            ]])->assertOk()
                ->assertJsonPath('data.results.0.record.status', 'completed');
        }

        $this->push($token, $contextKey, 'sales-finish-batch', [[
            'operation_id' => 'sales-finish-operation',
            'entity' => 'sales_journeys',
            'action' => 'finish',
            'record_id' => $journeyId,
            'base_version' => (string) $started->json('data.results.0.version'),
            'payload' => ['end_odometer' => 4012],
        ]])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.record.status', 'completed')
            ->assertJsonPath('data.results.0.record.start_odometer', 4000)
            ->assertJsonPath('data.results.0.record.end_odometer', 4012)
            ->assertJsonPath('data.results.0.record.distance_km', 12);

        $this->assertDatabaseHas('sales_journeys', ['id' => $journeyId, 'status' => 'completed']);
        $this->assertDatabaseCount('sales_visits', 1);
        $this->assertDatabaseCount('customers', 2);
    }

    private function push(string $token, string $contextKey, string $batchId, array $operations)
    {
        return $this->withFreshToken($token)->postJson('/api/v1/operational/sync/push', [
            'context_key' => $contextKey,
            'batch_id' => $batchId,
            'operations' => $operations,
        ]);
    }

    private function contextKey(string $token): string
    {
        return (string) $this->withFreshToken($token)
            ->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->json('data.sync.context_key');
    }

    private function context(): array
    {
        $area = Area::query()->create(['code' => 'SLS-SYNC-AREA', 'name_ar' => 'منطقة مزامنة المبيعات', 'status' => 'active']);
        $vehicle = Vehicle::query()->create(['code' => 'SLS-SYNC-VEH', 'plate_number' => 'SLS-SYNC-PLATE', 'status' => 'active', 'current_odometer' => 4000]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id, 'code' => 'SLS-SYNC-WH',
            'name' => 'مستودع مزامنة المبيعات', 'type' => 'vehicle', 'status' => 'active',
        ]);
        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'SLS-SYNC-SOURCE-WH',
            'name' => 'المستودع الرئيسي لمزامنة المبيعات',
            'type' => 'main',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'SLS-SYNC-REP', 'name' => 'مندوب مزامنة المبيعات',
            'type' => User::ROLE_SALES_REPRESENTATIVE, 'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id, 'vehicle_id' => $vehicle->id,
            'sales_representative_id' => $representative->id,
            'code' => 'SLS-SYNC-ROUTE', 'name' => 'خط مزامنة المبيعات',
            'visit_days' => [], 'status' => 'active',
        ]);
        VehicleLoad::query()->create([
            'load_number' => 'SLS-SYNC-LOAD-TODAY',
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
        Customer::query()->create([
            'code' => 'SLS-SYNC-CUS', 'name' => 'عميل مزامنة المبيعات',
            'area_id' => $area->id, 'route_id' => $route->id, 'status' => 'active',
        ]);

        return compact('representative', 'route');
    }

    private function salesUser(Employee $representative): User
    {
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE, 'status' => 'active']);
        $user->syncRoles([User::ROLE_SALES_REPRESENTATIVE]);
        $representative->update(['user_id' => $user->id]);

        return $user;
    }

    private function tokenFor(User $user, string $deviceId): string
    {
        $token = $user->createToken('sales-field-sync-test', [(string) config('mobile_api.token_ability')]);
        $token->accessToken->forceFill(['device_id' => $deviceId, 'device_name' => $deviceId])->save();

        return $token->plainTextToken;
    }

    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
