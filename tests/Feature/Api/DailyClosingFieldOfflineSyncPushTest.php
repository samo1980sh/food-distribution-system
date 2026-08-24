<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesJourney;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyClosingFieldOfflineSyncPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_inventory_and_cash_merge_from_the_same_base_version(): void
    {
        $context = $this->context();
        $context['route']->update(['driver_id' => null]);
        $sales = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );
        $salesToken = $this->tokenFor($sales, 'closing-sales-device');
        $salesContextKey = $this->contextKey($salesToken);
        $this->completedJourney($context, $sales);

        $opened = $this->withFreshToken($salesToken)
            ->postJson('/api/v1/operational/daily-closings/open-today', [
                'route_id' => $context['route']->id,
            ])
            ->assertCreated();

        $closingId = (int) $opened->json('data.id');
        $baseVersion = (string) $opened->json('data.sync_version');
        $expectedQuantity = (float) $opened->json('data.items.0.expected_quantity');

        $inventory = $this->push(
            $salesToken,
            $salesContextKey,
            'batch-closing-inventory-0001',
            [[
                'operation_id' => 'operation-closing-inventory-0001',
                'entity' => 'daily_closings',
                'action' => 'submit_inventory',
                'record_id' => $closingId,
                'base_version' => $baseVersion,
                'payload' => [
                    'items' => [[
                        'product_id' => $context['product']->id,
                        'actual_quantity' => $expectedQuantity,
                    ]],
                ],
            ]],
        )
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath(
                'data.results.0.record.field_handover.inventory.submitted',
                true,
            )
            ->assertJsonPath(
                'data.results.0.record.actions.can_submit_inventory',
                false,
            );

        $inventoryVersion = (string) $inventory->json('data.results.0.version');
        $this->assertMatchesRegularExpression('/^c:[1-9][0-9]*$/', $inventoryVersion);
        $this->assertNotSame($baseVersion, $inventoryVersion);

        $cash = $this->push(
            $salesToken,
            $salesContextKey,
            'batch-closing-cash-0001',
            [[
                'operation_id' => 'operation-closing-cash-0001',
                'entity' => 'daily_closings',
                'action' => 'submit_cash',
                'record_id' => $closingId,
                // The inventory submission changed the whole-record
                // version, but the independent cash section remains mergeable.
                'base_version' => $baseVersion,
                'payload' => [
                    'actual_cash_amount' => 0,
                ],
            ]],
        )
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath(
                'data.results.0.record.field_handover.cash.submitted',
                true,
            )
            ->assertJsonPath(
                'data.results.0.record.field_handover.complete',
                true,
            )
            ->assertJsonPath(
                'data.results.0.record.actions.can_submit_cash',
                false,
            );

        $this->assertMatchesRegularExpression(
            '/^c:[1-9][0-9]*$/',
            (string) $cash->json('data.results.0.version'),
        );

        $this->assertDatabaseHas('daily_closings', [
            'id' => $closingId,
            'inventory_submitted_by' => $sales->id,
            'cash_submitted_by' => $sales->id,
            'status' => 'draft',
        ]);
    }

    public function test_a_submitted_section_cannot_be_overwritten_by_a_new_operation(): void
    {
        $context = $this->context();
        $driver = $this->userForEmployee(User::ROLE_DRIVER, $context['driver']);
        $token = $this->tokenFor($driver, 'closing-lock-device');
        $contextKey = $this->contextKey($token);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/daily-closings/open-today', [
                'route_id' => $context['route']->id,
            ])
            ->assertCreated();

        $closingId = (int) $opened->json('data.id');
        $expectedQuantity = (float) $opened->json('data.items.0.expected_quantity');

        $first = $this->push(
            $token,
            $contextKey,
            'batch-closing-lock-0001',
            [[
                'operation_id' => 'operation-closing-lock-0001',
                'entity' => 'daily_closings',
                'action' => 'submit_inventory',
                'record_id' => $closingId,
                'base_version' => (string) $opened->json('data.sync_version'),
                'payload' => [
                    'items' => [[
                        'product_id' => $context['product']->id,
                        'actual_quantity' => $expectedQuantity,
                    ]],
                ],
            ]],
        )->assertOk();

        $this->push(
            $token,
            $contextKey,
            'batch-closing-lock-0002',
            [[
                'operation_id' => 'operation-closing-lock-0002',
                'entity' => 'daily_closings',
                'action' => 'submit_inventory',
                'record_id' => $closingId,
                'base_version' => (string) $first->json('data.results.0.version'),
                'payload' => [
                    'items' => [[
                        'product_id' => $context['product']->id,
                        'actual_quantity' => $expectedQuantity + 1,
                        'notes' => 'محاولة استبدال لاحقة',
                    ]],
                ],
            ]],
        )
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'failed')
            ->assertJsonPath('data.results.0.http_status', 403)
            ->assertJsonPath('data.results.0.code', 'http_403');

        $this->assertDatabaseHas('daily_closing_items', [
            'daily_closing_id' => $closingId,
            'product_id' => $context['product']->id,
            'actual_quantity' => $expectedQuantity,
        ]);
    }

    /** @param list<array<string, mixed>> $operations */
    private function push(
        string $token,
        string $contextKey,
        string $batchId,
        array $operations,
    ) {
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
        $area = Area::query()->create([
            'code' => 'SYNC-CLOSE-AREA',
            'name_ar' => 'منطقة مزامنة الإغلاق',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'SYNC-CLOSE-VEH',
            'plate_number' => 'SYNC-CLOSE-PLATE',
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'SYNC-CLOSE-WH',
            'name' => 'مستودع مزامنة الإغلاق',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'SYNC-CLOSE-DRV',
            'name' => 'سائق مزامنة الإغلاق',
            'type' => 'driver',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'SYNC-CLOSE-REP',
            'name' => 'مندوب مزامنة الإغلاق',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'SYNC-CLOSE-ROUTE',
            'name' => 'خط مزامنة الإغلاق',
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'SYNC-CLOSE-CAT',
            'name_ar' => 'تصنيف مزامنة الإغلاق',
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'SYNC-CLOSE-UNIT',
            'name_ar' => 'وحدة مزامنة الإغلاق',
            'symbol' => 'U',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'SYNC-CLOSE-SKU',
            'name_ar' => 'منتج مزامنة الإغلاق',
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
            'driver',
            'representative',
            'route',
            'product',
        );
    }

    private function userForEmployee(string $role, Employee $employee): User
    {
        $user = User::factory()->create(['role' => $role]);
        $employee->update(['user_id' => $user->id]);

        return $user;
    }

    /** @param array<string, mixed> $context */
    private function completedJourney(array $context, User $user): SalesJourney
    {
        return SalesJourney::query()->create([
            'journey_number' => 'FIELD-CLOSE-SYNC-JOURNEY',
            'journey_date' => today(),
            'route_id' => $context['route']->id,
            'vehicle_id' => $context['vehicle']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'driver_id' => null,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'created_by' => $user->id,
            'operation_source' => 'mobile_sales',
        ]);
    }

    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function tokenFor(User $user, string $deviceId): string
    {
        $token = $user->createToken(
            'daily-closing-sync-test',
            [(string) config('mobile_api.token_ability')],
        );
        $token->accessToken->forceFill([
            'device_id' => $deviceId,
            'device_name' => $deviceId,
        ])->save();

        return $token->plainTextToken;
    }
}
