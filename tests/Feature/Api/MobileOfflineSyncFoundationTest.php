<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\MobileSyncChange;
use App\Models\MobileSyncCheckpoint;
use App\Models\MobileSyncState;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use App\Services\Sales\CustomerPaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileOfflineSyncFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/operational/sync/status')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $this->postJson('/api/v1/operational/sync/pull', [])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_incremental_cursor_requires_a_valid_context_key(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $token = $this->tokenFor($user, 'sync-device-validation');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['context_key']);

        $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => 1,
                'context_key' => str_repeat('z', 64),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['context_key']);
    }

    public function test_bootstrap_and_status_expose_offline_sync_contract(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $token = $this->tokenFor($user, 'sync-device-bootstrap');

        $bootstrap = $this->withToken($token)
            ->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.sync.supports_cursor_pull', true)
            ->assertJsonPath('data.sync.supports_deleted_records', true)
            ->assertJsonPath('data.sync.offline_queue_supported', true)
            ->assertJsonPath('data.sync.push_mode', 'batch_idempotent')
            ->assertJsonPath('data.sync.batch_push_supported', true)
            ->assertJsonPath('data.sync.endpoints.push', '/api/v1/operational/sync/push');

        $contextKey = (string) $bootstrap->json('data.sync.context_key');
        $this->assertSame(64, strlen($contextKey));

        $this->withToken($token)
            ->getJson('/api/v1/operational/sync/status')
            ->assertOk()
            ->assertJsonPath('data.context_key', $contextKey)
            ->assertJsonPath('data.registry_version', 5)
            ->assertJsonPath('data.device.device_id', 'sync-device-bootstrap')
            ->assertJsonPath('data.reset_required', false);
    }

    public function test_initial_pull_returns_only_permitted_scoped_records(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);
        $first['representative']->update(['user_id' => $user->id]);
        $token = $this->tokenFor($user, 'sync-device-scoped');
        $contextKey = $this->contextKey($token);

        $response = $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => 0,
                'limit' => 500,
                'context_key' => $contextKey,
            ])
            ->assertOk()
            ->assertJsonPath('data.context_key', $contextKey)
            ->assertJsonPath('data.has_more', false);

        $changes = collect($response->json('data.changes'));
        $areaIds = $changes->where('entity', 'areas')->pluck('record_id')->all();
        $routeIds = $changes->where('entity', 'routes')->pluck('record_id')->all();
        $employeeIds = $changes->where('entity', 'employees')->pluck('record_id')->all();
        $categoryIds = $changes->where('entity', 'product_categories')->pluck('record_id')->all();
        $unitIds = $changes->where('entity', 'units')->pluck('record_id')->all();
        $customerIds = $changes->where('entity', 'customers')->pluck('record_id')->all();
        $invoiceIds = $changes->where('entity', 'sales_invoices')->pluck('record_id')->all();

        $this->assertContains($first['area']->id, $areaIds);
        $this->assertNotContains($second['area']->id, $areaIds);
        $this->assertContains($first['driver']->id, $employeeIds);
        $this->assertContains($first['representative']->id, $employeeIds);
        $this->assertNotContains($second['driver']->id, $employeeIds);
        $this->assertContains($first['category']->id, $categoryIds);
        $this->assertContains($second['category']->id, $categoryIds);
        $this->assertContains($first['unit']->id, $unitIds);
        $this->assertContains($second['unit']->id, $unitIds);
        $this->assertContains($first['route']->id, $routeIds);
        $this->assertNotContains($second['route']->id, $routeIds);
        $this->assertContains($first['customer']->id, $customerIds);
        $this->assertNotContains($second['customer']->id, $customerIds);
        $this->assertContains($first['invoice']->id, $invoiceIds);
        $this->assertNotContains($second['invoice']->id, $invoiceIds);

        $this->assertDatabaseHas('mobile_sync_states', [
            'user_id' => $user->id,
            'device_id' => 'sync-device-scoped',
            'context_key' => $contextKey,
        ]);
    }

    public function test_incremental_pull_returns_updates_and_deletion_tombstones(): void
    {
        $context = $this->context('A');
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);
        $context['representative']->update(['user_id' => $user->id]);
        $token = $this->tokenFor($user, 'sync-device-incremental');
        $contextKey = $this->contextKey($token);
        $cursor = $this->initialCursor($token, $contextKey);

        $context['customer']->update(['name' => 'Updated customer']);

        $updated = $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => $cursor,
                'context_key' => $contextKey,
            ])
            ->assertOk();

        $customerChange = collect($updated->json('data.changes'))
            ->first(fn (array $change): bool =>
                $change['entity'] === 'customers'
                && (int) $change['record_id'] === (int) $context['customer']->id
                && $change['operation'] === 'upsert');

        $this->assertNotNull($customerChange);
        $this->assertSame('Updated customer', $customerChange['record']['name']);
        $cursor = (int) $updated->json('data.cursor');

        $invoiceId = (int) $context['invoice']->id;
        $context['invoice']->delete();

        $deleted = $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => $cursor,
                'context_key' => $contextKey,
            ])
            ->assertOk();

        $tombstone = collect($deleted->json('data.changes'))
            ->first(fn (array $change): bool =>
                $change['entity'] === 'sales_invoices'
                && (int) $change['record_id'] === $invoiceId
                && $change['operation'] === 'delete');

        $this->assertNotNull($tombstone);
        $this->assertNull($tombstone['record']);
        $this->assertNull($tombstone['version']);
    }

    public function test_business_service_updates_are_recorded_for_incremental_sync(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $this->actingAs($manager);

        $payment = CustomerPayment::query()->create([
            'payment_number' => 'PAY-SYNC-A',
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'payment_date' => today(),
            'payment_method' => 'cash',
            'status' => 'draft',
            'amount' => 5,
        ]);
        $cursor = (int) (MobileSyncChange::query()->max('id') ?? 0);

        app(CustomerPaymentService::class)->confirm($payment);

        $change = MobileSyncChange::query()
            ->where('id', '>', $cursor)
            ->where('entity', 'customer_payments')
            ->where('record_id', $payment->id)
            ->where('operation', 'upsert')
            ->latest('id')
            ->first();

        $this->assertNotNull($change);
        $this->assertSame('confirmed', $payment->refresh()->status);
    }

    public function test_scope_dimension_change_sends_tombstone_to_previous_scope(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);
        $first['representative']->update(['user_id' => $user->id]);
        $token = $this->tokenFor($user, 'sync-device-scope-move');
        $contextKey = $this->contextKey($token);
        $cursor = $this->initialCursor($token, $contextKey);

        $first['customer']->update([
            'area_id' => $second['area']->id,
            'route_id' => $second['route']->id,
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => $cursor,
                'context_key' => $contextKey,
            ])
            ->assertOk();

        $customerChanges = collect($response->json('data.changes'))
            ->where('entity', 'customers')
            ->where('record_id', $first['customer']->id)
            ->values();

        $this->assertCount(1, $customerChanges);
        $this->assertSame('delete', $customerChanges->first()['operation']);
    }

    public function test_restricted_master_data_delete_preserves_records_without_tombstones(): void
    {
        $context = $this->context('A');
        $cursor = (int) (MobileSyncChange::query()->max('id') ?? 0);
        $areaId = (int) $context['area']->id;
        $routeId = (int) $context['route']->id;
        $customerId = (int) $context['customer']->id;
        $invoiceId = (int) $context['invoice']->id;
        $deleteWasBlocked = false;

        try {
            $context['area']->delete();
        } catch (QueryException) {
            $deleteWasBlocked = true;
        }

        $this->assertTrue($deleteWasBlocked);
        $this->assertDatabaseHas('areas', ['id' => $areaId]);
        $this->assertDatabaseHas('distribution_routes', ['id' => $routeId]);
        $this->assertDatabaseHas('customers', ['id' => $customerId]);
        $this->assertDatabaseHas('sales_invoices', ['id' => $invoiceId]);

        $changes = MobileSyncChange::query()
            ->where('id', '>', $cursor)
            ->get();

        $this->assertFalse($changes->contains(
            fn (MobileSyncChange $change): bool =>
                $change->entity === 'areas'
                && (int) $change->record_id === $areaId
                && $change->operation === 'delete',
        ));
        $this->assertFalse($changes->contains(
            fn (MobileSyncChange $change): bool =>
                $change->entity === 'routes'
                && (int) $change->record_id === $routeId
                && $change->operation === 'delete',
        ));
    }

    public function test_context_change_requires_full_reset(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);
        $first['representative']->update(['user_id' => $user->id]);
        $token = $this->tokenFor($user, 'sync-device-context-change');
        $oldContextKey = $this->contextKey($token);
        $cursor = $this->initialCursor($token, $oldContextKey);

        $second['route']->update([
            'sales_representative_id' => $first['representative']->id,
        ]);
        app(AccessScopeService::class)->forget($user);

        $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => $cursor,
                'context_key' => $oldContextKey,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'sync_context_changed')
            ->assertJsonPath('errors.sync.reset_required', true)
            ->assertJsonPath('errors.sync.cursor', 0);

        $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.cursor', fn (mixed $cursor): bool => (int) $cursor > 0);
    }

    public function test_compaction_preserves_full_sync_state_and_expires_old_cursors(): void
    {
        $context = $this->context('A');
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);
        $context['representative']->update(['user_id' => $user->id]);
        $token = $this->tokenFor($user, 'sync-device-compaction');
        $contextKey = $this->contextKey($token);
        $oldCursor = $this->initialCursor($token, $contextKey);

        $context['customer']->update(['name' => 'Customer after first update']);
        $context['customer']->update(['name' => 'Customer preserved after compaction']);
        $invoiceId = (int) $context['invoice']->id;
        $context['invoice']->delete();

        MobileSyncChange::query()->update([
            'changed_at' => now()->subDays(10),
        ]);

        $this->artisan('mobile-sync:prune', [
            '--days' => 1,
            '--apply' => true,
        ])->assertSuccessful();

        $checkpoint = MobileSyncCheckpoint::singleton();

        $this->assertGreaterThan($oldCursor, (int) $checkpoint->pruned_through_cursor);
        $this->assertDatabaseHas('mobile_sync_changes', [
            'entity' => 'customers',
            'record_id' => $context['customer']->id,
            'operation' => 'upsert',
        ]);
        $this->assertDatabaseMissing('mobile_sync_changes', [
            'entity' => 'sales_invoices',
            'record_id' => $invoiceId,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/operational/sync/status')
            ->assertOk()
            ->assertJsonPath('data.reset_required', true)
            ->assertJsonPath('data.reset_reason', 'sync_cursor_expired');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => $oldCursor,
                'context_key' => $contextKey,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'sync_cursor_expired')
            ->assertJsonPath('errors.sync.reset_required', true);

        $full = $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => 0,
                'limit' => 500,
                'context_key' => $contextKey,
            ])
            ->assertOk()
            ->assertJsonPath('data.has_more', false);

        $changes = collect($full->json('data.changes'));
        $customer = $changes
            ->where('entity', 'customers')
            ->where('record_id', $context['customer']->id)
            ->last();

        $this->assertNotNull($customer);
        $this->assertSame('upsert', $customer['operation']);
        $this->assertSame(
            'Customer preserved after compaction',
            $customer['record']['name'],
        );
        $this->assertFalse($changes
            ->where('entity', 'sales_invoices')
            ->where('record_id', $invoiceId)
            ->isNotEmpty());
        $this->assertGreaterThanOrEqual(
            (int) $checkpoint->pruned_through_cursor,
            (int) $full->json('data.cursor'),
        );
    }

    /** @return array<string, mixed> */
    private function context(string $suffix): array
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
            'vehicle_id' => $vehicle->id,
            'code' => 'WH-'.$suffix,
            'name' => 'مستودع '.$suffix,
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'SOURCE-WH-'.$suffix,
            'name' => 'مستودع مصدر '.$suffix,
            'type' => 'main',
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
        $category = ProductCategory::query()->create([
            'code' => 'CAT-'.$suffix,
            'name_ar' => 'تصنيف '.$suffix,
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'UNIT-'.$suffix,
            'name_ar' => 'وحدة '.$suffix,
            'symbol' => 'U',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'SKU-'.$suffix,
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
        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-'.$suffix,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'warehouse_id' => $warehouse->id,
            'sales_representative_id' => $representative->id,
            'invoice_date' => today(),
            'status' => 'draft',
            'payment_type' => 'cash',
            'total_amount' => 10,
        ]);
        VehicleLoad::query()->create([
            'load_number' => 'LOAD-'.$suffix,
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $warehouse->id,
            'load_date' => today(),
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
            'category',
            'unit',
            'product',
            'invoice',
        );
    }

    private function tokenFor(User $user, string $deviceId): string
    {
        $token = $user->createToken(
            'offline-sync-test',
            [(string) config('mobile_api.token_ability')],
        );

        $token->accessToken->forceFill([
            'device_id' => $deviceId,
            'device_name' => 'Offline Sync Test',
            'platform' => 'android',
            'last_seen_at' => now(),
        ])->save();

        return $token->plainTextToken;
    }

    private function contextKey(string $token): string
    {
        return (string) $this->withToken($token)
            ->getJson('/api/v1/operational/sync/status')
            ->assertOk()
            ->json('data.context_key');
    }

    private function initialCursor(string $token, string $contextKey): int
    {
        return (int) $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => 0,
                'limit' => 500,
                'context_key' => $contextKey,
            ])
            ->assertOk()
            ->json('data.cursor');
    }
}
