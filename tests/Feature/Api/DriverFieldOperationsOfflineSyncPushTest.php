<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesInvoice;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverFieldOperationsOfflineSyncPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_start_submit_delivery_and_finish_through_push_batch(): void
    {
        $context = $this->context();
        $user = $this->driverUser($context['driver']);
        $token = $this->tokenFor($user, 'driver-field-sync-device');
        $contextKey = $this->contextKey($token);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/driver-journeys/open-today')
            ->assertCreated();

        $journeyId = (int) $opened->json('data.id');
        $deliveryId = (int) $opened->json('data.deliveries.0.id');
        $invoiceItemId = (int) $opened->json('data.deliveries.0.items.0.sales_invoice_item_id');

        $started = $this->push($token, $contextKey, 'driver-start-batch', [[
            'operation_id' => 'driver-start-operation',
            'entity' => 'driver_journeys',
            'action' => 'start',
            'record_id' => $journeyId,
            'base_version' => (string) $opened->json('data.sync_version'),
            'payload' => ['start_odometer' => 100],
        ]])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.record.status', 'in_progress');

        $delivery = $this->withFreshToken($token)
            ->getJson("/api/v1/operational/driver-deliveries/{$deliveryId}")
            ->assertOk();

        $this->push($token, $contextKey, 'driver-outcome-batch', [[
            'operation_id' => 'driver-outcome-operation',
            'entity' => 'driver_deliveries',
            'action' => 'submit_outcome',
            'record_id' => $deliveryId,
            'base_version' => (string) $delivery->json('data.sync_version'),
            'payload' => [
                'outcome' => 'delivered',
                'recipient_name' => 'مستلم المزامنة',
                'items' => [[
                    'sales_invoice_item_id' => $invoiceItemId,
                    'delivered_quantity' => 5,
                    'returned_quantity' => 0,
                ]],
            ],
        ]])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.record.status', 'delivered');

        $this->push($token, $contextKey, 'driver-finish-batch', [[
            'operation_id' => 'driver-finish-operation',
            'entity' => 'driver_journeys',
            'action' => 'finish',
            'record_id' => $journeyId,
            'base_version' => (string) $started->json('data.results.0.version'),
            'payload' => ['end_odometer' => 125],
        ]])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.record.status', 'completed');

        $this->assertDatabaseHas('driver_journeys', [
            'id' => $journeyId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('driver_deliveries', [
            'id' => $deliveryId,
            'status' => 'delivered',
        ]);
    }

    /** @param list<array<string, mixed>> $operations */
    private function push(string $token, string $contextKey, string $batchId, array $operations)
    {
        return $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sync/push', [
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

    /** @return array<string, mixed> */
    private function context(): array
    {
        $area = Area::query()->create(['code' => 'DRV-SYNC-AREA', 'name_ar' => 'منطقة مزامنة السائق', 'status' => 'active']);
        $vehicle = Vehicle::query()->create(['code' => 'DRV-SYNC-VEH', 'plate_number' => 'DRV-SYNC-PLATE', 'status' => 'active']);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'DRV-SYNC-WH',
            'name' => 'مستودع مزامنة السائق',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'DRV-SYNC-EMP',
            'name' => 'سائق مزامنة الرحلة',
            'type' => User::ROLE_DRIVER,
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'DRV-SYNC-REP',
            'name' => 'مندوب مزامنة الرحلة',
            'type' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'DRV-SYNC-ROUTE',
            'name' => 'خط مزامنة السائق',
            'visit_days' => [],
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'code' => 'DRV-SYNC-CUS',
            'name' => 'عميل مزامنة السائق',
            'area_id' => $area->id,
            'route_id' => $route->id,
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create(['code' => 'DRV-SYNC-CAT', 'name_ar' => 'تصنيف مزامنة السائق', 'status' => 'active']);
        $unit = Unit::query()->create(['code' => 'DRV-SYNC-UNIT', 'name_ar' => 'وحدة', 'symbol' => 'U', 'status' => 'active']);
        $product = Product::query()->create([
            'sku' => 'DRV-SYNC-SKU',
            'name_ar' => 'منتج مزامنة السائق',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 5,
            'sale_price' => 10,
            'wholesale_price' => 9,
            'status' => 'active',
        ]);
        $invoice = SalesInvoice::withoutEvents(fn () => SalesInvoice::query()->create([
            'invoice_number' => 'DRV-SYNC-INV',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'warehouse_id' => $warehouse->id,
            'sales_representative_id' => $representative->id,
            'invoice_date' => today(),
            'status' => 'confirmed',
            'payment_type' => 'cash',
            'total_amount' => 50,
        ]));
        $invoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10,
            'line_total' => 50,
        ]);

        return compact('driver');
    }

    private function driverUser(Employee $driver): User
    {
        $user = User::factory()->create(['role' => User::ROLE_DRIVER, 'status' => 'active']);
        $user->syncRoles([User::ROLE_DRIVER]);
        $driver->update(['user_id' => $user->id]);

        return $user;
    }

    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function tokenFor(User $user, string $deviceId): string
    {
        $token = $user->createToken(
            'driver-field-sync-test',
            [(string) config('mobile_api.token_ability')],
        );
        $token->accessToken->forceFill([
            'device_id' => $deviceId,
            'device_name' => $deviceId,
        ])->save();

        return $token->plainTextToken;
    }
}
