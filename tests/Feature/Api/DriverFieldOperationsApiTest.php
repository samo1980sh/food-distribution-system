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

class DriverFieldOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_open_start_complete_delivery_and_finish_today_journey(): void
    {
        $context = $this->context();
        $user = $this->driverUser($context['driver']);
        $token = $this->tokenFor($user);

        $this->withFreshToken($token)
            ->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.modules.driver_journeys', true)
            ->assertJsonPath('data.modules.driver_deliveries', true)
            ->assertJsonPath('data.write.driver_journeys.open_today', true)
            ->assertJsonPath('data.write.driver_journeys.start', true)
            ->assertJsonPath('data.write.driver_journeys.finish', true)
            ->assertJsonPath('data.write.driver_deliveries.submit_outcome', true);

        $open = $this->withFreshToken($token)->postJson('/api/v1/operational/driver-journeys/open-today')
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.sales_representative.id', $context['representative']->id)
            ->assertJsonCount(1, 'data.deliveries');

        $journeyId = (int) $open->json('data.id');

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/driver-journeys/open-today')
            ->assertOk()
            ->assertJsonPath('data.id', $journeyId);
        $deliveryId = (int) $open->json('data.deliveries.0.id');
        $invoiceItemId = (int) $open->json('data.deliveries.0.items.0.sales_invoice_item_id');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/driver-journeys/{$journeyId}/start", [
                'start_odometer' => 1200,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/driver-deliveries/{$deliveryId}/submit-outcome", [
                'outcome' => 'delivered',
                'recipient_name' => 'مستلم العميل',
                'proof_note' => 'تم التسليم بحالة سليمة.',
                'items' => [[
                    'sales_invoice_item_id' => $invoiceItemId,
                    'delivered_quantity' => 5,
                    'returned_quantity' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.return_required', false);

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/driver-journeys/{$journeyId}/finish", [
                'end_odometer' => 1235,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.summary.pending', 0);
    }

    public function test_partial_delivery_records_physical_return_without_creating_stock_or_sales_return(): void
    {
        $context = $this->context();
        $user = $this->driverUser($context['driver']);
        $token = $this->tokenFor($user);

        $open = $this->withFreshToken($token)->postJson('/api/v1/operational/driver-journeys/open-today')->assertCreated();
        $journeyId = (int) $open->json('data.id');
        $deliveryId = (int) $open->json('data.deliveries.0.id');
        $invoiceItemId = (int) $open->json('data.deliveries.0.items.0.sales_invoice_item_id');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/driver-journeys/{$journeyId}/start", [
                'start_odometer' => 10,
            ])
            ->assertOk();

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/driver-deliveries/{$deliveryId}/submit-outcome", [
                'outcome' => 'partial',
                'recipient_name' => 'مستلم جزئي',
                'items' => [[
                    'sales_invoice_item_id' => $invoiceItemId,
                    'delivered_quantity' => 3,
                    'returned_quantity' => 2,
                    'notes' => 'رفض العميل عبوتين.',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.delivered_quantity', '3.000')
            ->assertJsonPath('data.returned_quantity', '2.000')
            ->assertJsonPath('data.return_required', true);

        $this->assertDatabaseCount('sales_returns', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_journey_cannot_finish_while_a_delivery_is_pending(): void
    {
        $context = $this->context();
        $user = $this->driverUser($context['driver']);
        $token = $this->tokenFor($user);

        $open = $this->withFreshToken($token)->postJson('/api/v1/operational/driver-journeys/open-today')->assertCreated();
        $journeyId = (int) $open->json('data.id');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/driver-journeys/{$journeyId}/start", [
                'start_odometer' => 10,
            ])
            ->assertOk();

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/driver-journeys/{$journeyId}/finish", [
                'end_odometer' => 20,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'pending_deliveries_exist');
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        $area = Area::query()->create([
            'code' => 'DRV-AREA',
            'name_ar' => 'منطقة السائق',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'DRV-VEH',
            'plate_number' => 'DRV-PLATE',
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'DRV-WH',
            'name' => 'مستودع سيارة السائق',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'DRV-EMP',
            'name' => 'سائق العمليات',
            'type' => User::ROLE_DRIVER,
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'DRV-REP',
            'name' => 'مندوب العمليات',
            'type' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'DRV-ROUTE',
            'name' => 'خط السائق',
            'visit_days' => [],
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'code' => 'DRV-CUS',
            'name' => 'عميل التسليم',
            'area_id' => $area->id,
            'route_id' => $route->id,
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'DRV-CAT',
            'name_ar' => 'تصنيف التسليم',
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'DRV-UNIT',
            'name_ar' => 'وحدة',
            'symbol' => 'U',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'DRV-SKU',
            'name_ar' => 'منتج التسليم',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 5,
            'sale_price' => 10,
            'wholesale_price' => 9,
            'status' => 'active',
        ]);
        $invoice = SalesInvoice::withoutEvents(fn () => SalesInvoice::query()->create([
            'invoice_number' => 'DRV-INV',
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

        return compact(
            'area',
            'vehicle',
            'warehouse',
            'driver',
            'representative',
            'route',
            'customer',
            'product',
            'invoice',
        );
    }

    private function driverUser(Employee $driver): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DRIVER,
            'status' => 'active',
        ]);
        $user->syncRoles([User::ROLE_DRIVER]);
        $driver->update(['user_id' => $user->id]);

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
            'driver-field-api-test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;
    }
}
