<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\MobileSyncPushBatch;
use App\Models\MobileSyncPushOperation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileOfflineSyncPushBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/v1/operational/sync/push', [])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_bootstrap_and_status_expose_batch_push_contract(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager, 'push-contract-device');

        $this->withToken($token)
            ->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.sync.batch_push_supported', true)
            ->assertJsonPath('data.sync.push_mode', 'batch_idempotent')
            ->assertJsonPath('data.sync.conflict_strategy', 'server_wins_pull_then_retry')
            ->assertJsonPath('data.sync.endpoints.push', '/api/v1/operational/sync/push');

        $this->withToken($token)
            ->getJson('/api/v1/operational/sync/status')
            ->assertOk()
            ->assertJsonPath('data.limits.max_push_operations', 50)
            ->assertJsonPath('data.limits.max_push_operation_kb', 256);
    }


    public function test_context_mismatch_rejects_the_entire_batch(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager, 'push-context-mismatch-device');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sync/push', [
                'context_key' => str_repeat('a', 64),
                'batch_id' => 'batch-context-mismatch-0001',
                'operations' => [[
                    'operation_id' => 'operation-context-mismatch-0001',
                    'entity' => 'sales_invoices',
                    'action' => 'create',
                    'payload' => [],
                ]],
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'sync_context_changed')
            ->assertJsonPath('errors.sync.reset_required', true);

        $this->assertDatabaseCount('mobile_sync_push_batches', 0);
        $this->assertDatabaseCount('mobile_sync_push_operations', 0);
    }

    public function test_batch_create_and_exact_batch_replay_are_idempotent(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager, 'push-batch-replay-device');
        $contextKey = $this->contextKey($token);
        $payload = [
            'context_key' => $contextKey,
            'batch_id' => 'batch-create-invoice-0001',
            'operations' => [[
                'operation_id' => 'operation-create-invoice-0001',
                'entity' => 'sales_invoices',
                'action' => 'create',
                'payload' => $this->invoicePayload($context, 'push-invoice-0001'),
            ]],
        ];

        $first = $this->withToken($token)
            ->postJson('/api/v1/operational/sync/push', $payload)
            ->assertOk()
            ->assertJsonPath('data.replayed', false)
            ->assertJsonPath('data.summary.applied', 1)
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.code', 'created')
            ->assertJsonPath('data.results.0.record.status', 'draft');

        $invoiceId = (int) $first->json('data.results.0.record_id');
        $this->assertGreaterThan(0, $invoiceId);
        $this->assertMatchesRegularExpression('/^c:[1-9][0-9]*$/', (string) $first->json('data.results.0.version'));

        $this->withToken($token)
            ->postJson('/api/v1/operational/sync/push', $payload)
            ->assertOk()
            ->assertJsonPath('data.replayed', true)
            ->assertJsonPath('data.results.0.record_id', $invoiceId);

        $this->assertSame(1, SalesInvoice::withoutGlobalScopes()->where('client_reference', 'push-invoice-0001')->count());
        $this->assertSame(1, MobileSyncPushBatch::query()->count());
        $this->assertSame(1, MobileSyncPushOperation::query()->count());
    }


    public function test_stale_processing_batch_is_resumed_using_operation_idempotency(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $deviceId = 'push-stale-batch-device';
        $token = $this->tokenFor($manager, $deviceId);
        $contextKey = $this->contextKey($token);
        $payload = [
            'context_key' => $contextKey,
            'batch_id' => 'batch-stale-resume-0001',
            'operations' => [[
                'operation_id' => 'operation-stale-resume-0001',
                'entity' => 'sales_invoices',
                'action' => 'create',
                'payload' => $this->invoicePayload($context, 'push-stale-invoice-0001'),
            ]],
        ];

        $batch = MobileSyncPushBatch::query()->create([
            'user_id' => $manager->id,
            'device_id' => $deviceId,
            'batch_id' => $payload['batch_id'],
            'request_hash' => $this->requestHash($payload),
            'status' => 'processing',
            'operation_count' => 1,
        ]);
        $batch->forceFill(['updated_at' => now()->subMinutes(10)])->saveQuietly();

        $this->withToken($token)
            ->postJson('/api/v1/operational/sync/push', $payload)
            ->assertOk()
            ->assertJsonPath('data.summary.applied', 1)
            ->assertJsonPath('data.results.0.code', 'created');

        $this->assertSame('completed', $batch->refresh()->status);
        $this->assertDatabaseHas('sales_invoices', ['client_reference' => 'push-stale-invoice-0001']);
    }

    public function test_operation_replay_across_batches_and_id_reuse_conflicts_are_reported(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager, 'push-operation-replay-device');
        $contextKey = $this->contextKey($token);
        $operation = [
            'operation_id' => 'operation-replay-invoice-0001',
            'entity' => 'sales_invoices',
            'action' => 'create',
            'payload' => $this->invoicePayload($context, 'push-replay-invoice-0001'),
        ];

        $this->push($token, $contextKey, 'batch-operation-replay-0001', [$operation])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied');

        $this->push($token, $contextKey, 'batch-operation-replay-0002', [$operation])
            ->assertOk()
            ->assertJsonPath('data.summary.replayed', 1)
            ->assertJsonPath('data.results.0.status', 'replayed')
            ->assertJsonPath('data.results.0.replay_source', 'operation_id');

        $changed = $operation;
        $changed['payload']['notes'] = 'محتوى مختلف';

        $this->push($token, $contextKey, 'batch-operation-replay-0003', [$changed])
            ->assertOk()
            ->assertJsonPath('data.summary.conflicts', 1)
            ->assertJsonPath('data.results.0.code', 'operation_idempotency_conflict');

        $batchPayload = [
            'context_key' => $contextKey,
            'batch_id' => 'batch-id-conflict-0001',
            'operations' => [[
                ...$operation,
                'operation_id' => 'operation-batch-id-conflict-0001',
            ]],
        ];

        $this->withToken($token)->postJson('/api/v1/operational/sync/push', $batchPayload)->assertOk();
        $batchPayload['operations'][0]['payload']['notes'] = 'تغيير الدفعة';

        $this->withToken($token)
            ->postJson('/api/v1/operational/sync/push', $batchPayload)
            ->assertConflict()
            ->assertJsonPath('code', 'batch_idempotency_conflict');
    }

    public function test_stale_update_returns_server_record_without_overwriting_it(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager, 'push-conflict-device');
        $contextKey = $this->contextKey($token);

        $created = $this->push($token, $contextKey, 'batch-conflict-create-0001', [[
            'operation_id' => 'operation-conflict-create-0001',
            'entity' => 'sales_invoices',
            'action' => 'create',
            'payload' => $this->invoicePayload($context, 'push-conflict-invoice-0001'),
        ]])->assertOk();

        $invoiceId = (int) $created->json('data.results.0.record_id');
        $baseVersion = (string) $created->json('data.results.0.version');
        $invoice = SalesInvoice::withoutGlobalScopes()->findOrFail($invoiceId);
        $invoice->update(['notes' => 'تعديل من الخادم']);

        $this->push($token, $contextKey, 'batch-conflict-update-0001', [[
            'operation_id' => 'operation-conflict-update-0001',
            'entity' => 'sales_invoices',
            'action' => 'update',
            'record_id' => $invoiceId,
            'base_version' => $baseVersion,
            'payload' => ['notes' => 'تعديل قديم من الجهاز'],
        ]])
            ->assertOk()
            ->assertJsonPath('data.summary.conflicts', 1)
            ->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.code', 'sync_version_conflict')
            ->assertJsonPath('data.results.0.errors.conflict.resolution', 'server_wins_pull_then_retry')
            ->assertJsonPath('data.results.0.errors.conflict.server_record.notes', 'تعديل من الخادم');

        $this->assertSame('تعديل من الخادم', $invoice->refresh()->notes);
    }

    public function test_partial_batch_keeps_valid_operation_and_reports_validation_failure(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager, 'push-partial-device');
        $contextKey = $this->contextKey($token);

        $this->push($token, $contextKey, 'batch-partial-0001', [
            [
                'operation_id' => 'operation-partial-valid-0001',
                'entity' => 'sales_invoices',
                'action' => 'create',
                'payload' => $this->invoicePayload($context, 'push-partial-invoice-0001'),
            ],
            [
                'operation_id' => 'operation-partial-invalid-0001',
                'entity' => 'customer_payments',
                'action' => 'create',
                'payload' => ['client_reference' => 'push-invalid-payment-0001'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.applied', 1)
            ->assertJsonPath('data.summary.failed', 1)
            ->assertJsonPath('data.results.1.code', 'validation_failed')
            ->assertJsonPath('data.results.1.http_status', 422);

        $this->assertDatabaseHas('sales_invoices', ['client_reference' => 'push-partial-invoice-0001']);
    }

    public function test_scoped_user_cannot_update_out_of_scope_record_through_batch(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $managerToken = $this->tokenFor($manager, 'push-scope-manager');
        $outside = $this->push(
            $managerToken,
            $this->contextKey($managerToken),
            'batch-scope-outside-create-0001',
            [[
                'operation_id' => 'operation-scope-outside-create-0001',
                'entity' => 'sales_invoices',
                'action' => 'create',
                'payload' => $this->invoicePayload($second, 'push-outside-invoice-0001'),
            ]],
        )->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $representative = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);
        $first['representative']->update(['user_id' => $representative->id]);
        $token = $this->tokenFor($representative, 'push-scope-representative');

        $this->push($token, $this->contextKey($token), 'batch-scope-denied-0001', [[
            'operation_id' => 'operation-scope-denied-0001',
            'entity' => 'sales_invoices',
            'action' => 'update',
            'record_id' => (int) $outside->json('data.results.0.record_id'),
            'base_version' => (string) $outside->json('data.results.0.version'),
            'payload' => ['notes' => 'محاولة خارج النطاق'],
        ]])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'failed')
            ->assertJsonPath('data.results.0.code', 'http_404');
    }

    public function test_batch_action_reuses_business_service_and_records_incremental_change(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager, 'push-confirm-device');
        $contextKey = $this->contextKey($token);

        $created = $this->push($token, $contextKey, 'batch-confirm-create-0001', [[
            'operation_id' => 'operation-confirm-create-0001',
            'entity' => 'sales_invoices',
            'action' => 'create',
            'payload' => $this->invoicePayload($context, 'push-confirm-invoice-0001'),
        ]])->assertOk();

        $invoiceId = (int) $created->json('data.results.0.record_id');
        $version = (string) $created->json('data.results.0.version');

        $this->push($token, $contextKey, 'batch-confirm-action-0001', [[
            'operation_id' => 'operation-confirm-action-0001',
            'entity' => 'sales_invoices',
            'action' => 'confirm',
            'record_id' => $invoiceId,
            'base_version' => $version,
        ]])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.record.status', 'confirmed');

        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $context['warehouse']->id,
            'product_id' => $context['product']->id,
            'quantity' => 18,
        ]);

        $pulled = $this->withToken($token)
            ->postJson('/api/v1/operational/sync/pull', [
                'cursor' => 0,
                'context_key' => $contextKey,
                'limit' => 500,
            ])
            ->assertOk();

        $invoiceChange = collect($pulled->json('data.changes'))
            ->where('entity', 'sales_invoices')
            ->where('record_id', $invoiceId)
            ->last();

        $this->assertNotNull($invoiceChange);
        $this->assertSame('confirmed', $invoiceChange['record']['status']);
    }

    /** @param list<array<string, mixed>> $operations */
    private function push(string $token, string $contextKey, string $batchId, array $operations)
    {
        return $this->withToken($token)
            ->postJson('/api/v1/operational/sync/push', [
                'context_key' => $contextKey,
                'batch_id' => $batchId,
                'operations' => $operations,
            ]);
    }

    private function contextKey(string $token): string
    {
        return (string) $this->withToken($token)
            ->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->json('data.sync.context_key');
    }

    /** @return array<string, mixed> */
    private function context(string $suffix): array
    {
        $area = Area::query()->create([
            'code' => 'PUSH-AREA-'.$suffix,
            'name_ar' => 'منطقة '.$suffix,
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'PUSH-VEH-'.$suffix,
            'plate_number' => 'PUSH-PLATE-'.$suffix,
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'PUSH-WH-'.$suffix,
            'name' => 'مستودع '.$suffix,
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'PUSH-DRV-'.$suffix,
            'name' => 'سائق '.$suffix,
            'type' => 'driver',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'PUSH-REP-'.$suffix,
            'name' => 'مندوب '.$suffix,
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'PUSH-ROUTE-'.$suffix,
            'name' => 'خط '.$suffix,
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'code' => 'PUSH-CUS-'.$suffix,
            'name' => 'عميل '.$suffix,
            'area_id' => $area->id,
            'route_id' => $route->id,
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'PUSH-CAT-'.$suffix,
            'name_ar' => 'تصنيف '.$suffix,
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'PUSH-UNIT-'.$suffix,
            'name_ar' => 'وحدة '.$suffix,
            'symbol' => 'U',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'PUSH-SKU-'.$suffix,
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

        return compact('area', 'vehicle', 'warehouse', 'driver', 'representative', 'route', 'customer', 'product');
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    private function invoicePayload(array $context, string $clientReference): array
    {
        return [
            'client_reference' => $clientReference,
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'invoice_date' => today()->toDateString(),
            'payment_type' => 'cash',
            'paid_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'items' => [[
                'product_id' => $context['product']->id,
                'quantity' => 2,
                'unit_price' => 10,
                'discount_amount' => 0,
            ]],
        ];
    }


    /** @param array<string, mixed> $payload */
    private function requestHash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->normalize($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }

    private function tokenFor(User $user, string $deviceId): string
    {
        $token = $user->createToken(
            'sync-push-test',
            [(string) config('mobile_api.token_ability')],
        );
        $token->accessToken->forceFill([
            'device_id' => $deviceId,
            'device_name' => $deviceId,
        ])->save();

        return $token->plainTextToken;
    }
}
