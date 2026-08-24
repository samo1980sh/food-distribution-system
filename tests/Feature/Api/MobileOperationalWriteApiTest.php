<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\DriverJourney;
use App\Models\Employee;
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

class MobileOperationalWriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_write_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/operational/sales-invoices', [])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_bootstrap_exposes_role_aware_write_capabilities(): void
    {
        $context = $this->context('A');
        $representative = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );

        $this->withToken($this->tokenFor($representative))
            ->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.field_workspace.role', User::ROLE_SALES_REPRESENTATIVE)
            ->assertJsonPath('data.field_workspace.unified', true)
            ->assertJsonPath('data.write.enabled', true)
            ->assertJsonPath('data.write.idempotent_create', true)
            ->assertJsonPath('data.write.vehicle_loads.acknowledge', true)
            ->assertJsonPath('data.write.sales_invoices.create', true)
            ->assertJsonPath('data.write.sales_invoices.update', true)
            ->assertJsonPath('data.write.sales_invoices.confirm', false)
            ->assertJsonPath('data.write.customer_payments.create', true)
            ->assertJsonPath('data.write.sales_returns.create', true)
            ->assertJsonPath('data.write.vehicle_expenses.create', true)
            ->assertJsonPath('data.write.daily_closings.open_today', true)
            ->assertJsonPath('data.write.daily_closings.submit_inventory', true)
            ->assertJsonPath('data.write.daily_closings.submit_cash', true)
            ->assertJsonPath('data.sync.write_api_enabled', true)
            ->assertJsonPath('data.sync.offline_queue_supported', true);
    }

    public function test_sales_representative_create_is_scoped_and_idempotent(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $first['representative'],
        );
        $token = $this->tokenFor($user);
        $payload = $this->invoicePayload($first, 'mobile-invoice-A-0001');

        $created = $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $payload)
            ->assertCreated()
            ->assertJsonPath('data.client_reference', 'mobile-invoice-A-0001')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonPath('meta.idempotency.replayed', false);

        $invoiceId = (int) $created->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $invoiceId)
            ->assertJsonPath('meta.idempotency.replayed', true);

        $this->assertDatabaseCount('sales_invoices', 1);
        $this->assertDatabaseCount('driver_deliveries', 0);
        $this->assertDatabaseCount('driver_delivery_items', 0);

        $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', [
                ...$payload,
                'paid_amount' => 5,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $this->invoicePayload(
                $second,
                'mobile-invoice-B-0001',
            ))
            ->assertForbidden();

        $this->assertDatabaseMissing('sales_invoices', [
            'client_reference' => 'mobile-invoice-B-0001',
        ]);
    }

    public function test_invoice_confirmation_does_not_depend_on_completed_legacy_driver_journey(): void
    {
        $context = $this->context('COMPLETED-JOURNEY');
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );
        DriverJourney::query()->create([
            'journey_date' => today(),
            'route_id' => $context['route']->id,
            'vehicle_id' => $context['vehicle']->id,
            'warehouse_id' => $context['warehouse']->id,
            'driver_id' => $context['driver']->id,
            'sales_representative_id' => $context['representative']->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/operational/sales-invoices', $this->invoicePayload(
                $context,
                'invoice-after-completed-driver-journey',
            ))
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseCount('driver_deliveries', 0);
        $this->assertDatabaseCount('driver_delivery_items', 0);
        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $context['warehouse']->id,
            'product_id' => $context['product']->id,
            'quantity' => 18,
        ]);
    }

    public function test_invoice_preserves_multi_batch_allocation_without_driver_delivery(): void
    {
        $context = $this->context('MULTI-BATCH');
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );
        foreach (['BATCH-A', 'BATCH-B'] as $batchNumber) {
            StockBalance::query()->create([
                'warehouse_id' => $context['warehouse']->id,
                'product_id' => $context['product']->id,
                'batch_number' => $batchNumber,
                'batch_key' => $batchNumber,
                'expiry_key' => '',
                'quantity' => 20,
                'average_unit_cost' => 5,
            ]);
        }
        $payload = $this->invoicePayload(
            $context,
            'mobile-invoice-multi-batch-0001',
        );
        $payload['items'] = [
            [
                'product_id' => $context['product']->id,
                'batch_number' => 'BATCH-A',
                'quantity' => 20,
                'unit_price' => 10,
                'discount_amount' => 0,
            ],
            [
                'product_id' => $context['product']->id,
                'batch_number' => 'BATCH-B',
                'quantity' => 20,
                'unit_price' => 10,
                'discount_amount' => 0,
            ],
        ];

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/operational/sales-invoices', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total_amount', '400.00')
            ->assertJsonCount(2, 'data.items');

        $invoiceId = (int) $response->json('data.id');
        $this->assertDatabaseCount('driver_deliveries', 0);
        $this->assertDatabaseCount('driver_delivery_items', 0);
        $this->assertDatabaseHas('sales_invoice_items', [
            'sales_invoice_id' => $invoiceId,
            'batch_number' => 'BATCH-A',
            'quantity' => 20,
        ]);
        $this->assertDatabaseHas('sales_invoice_items', [
            'sales_invoice_id' => $invoiceId,
            'batch_number' => 'BATCH-B',
            'quantity' => 20,
        ]);
        foreach (['BATCH-A', 'BATCH-B'] as $batchNumber) {
            $this->assertDatabaseHas('stock_balances', [
                'warehouse_id' => $context['warehouse']->id,
                'product_id' => $context['product']->id,
                'batch_key' => $batchNumber,
                'quantity' => 0,
            ]);
        }
    }

    public function test_api_rejects_internally_inconsistent_but_in_scope_context(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');

        $second['route']->update([
            'sales_representative_id' => $first['representative']->id,
        ]);

        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $first['representative'],
        );
        $payload = $this->invoicePayload(
            $second,
            'context-mismatch-invoice-0001',
        );
        $payload['customer_id'] = $first['customer']->id;
        $payload['sales_representative_id'] = $first['representative']->id;

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/operational/sales-invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['route_id']);

        $this->assertDatabaseMissing('sales_invoices', [
            'client_reference' => 'context-mismatch-invoice-0001',
        ]);
    }

    public function test_field_api_rejects_credit_limit_override_request(): void
    {
        $context = $this->context('A');
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );
        $payload = $this->invoicePayload(
            $context,
            'field-credit-override-0001',
        );
        $payload['credit_limit_override_requested'] = true;
        $payload['credit_limit_override_reason'] = 'محاولة تجاوز من مستخدم ميداني';

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/operational/sales-invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['credit_limit_override_requested']);

        $this->assertDatabaseMissing('sales_invoices', [
            'client_reference' => 'field-credit-override-0001',
        ]);
    }

    public function test_sales_representative_cannot_update_or_delete_an_auto_confirmed_invoice(): void
    {
        $context = $this->context('A');
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );
        $token = $this->tokenFor($user);

        $response = $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $this->invoicePayload(
                $context,
                'field-invoice-0001',
            ))
            ->assertCreated();

        $invoiceId = (int) $response->json('data.id');

        $this->withToken($token)
            ->patchJson('/api/v1/operational/sales-invoices/'.$invoiceId, [
                'notes' => 'Updated from field app',
                'items' => [[
                    'product_id' => $context['product']->id,
                    'quantity' => 3,
                    'unit_price' => 10,
                    'discount_amount' => 0,
                ]],
            ])
            ->assertForbidden();

        $this->withToken($token)
            ->deleteJson('/api/v1/operational/sales-invoices/'.$invoiceId)
            ->assertForbidden();

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoiceId,
            'status' => 'confirmed',
        ]);
    }

    public function test_field_user_cannot_confirm_invoice(): void
    {
        $context = $this->context('A');
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );
        $token = $this->tokenFor($user);

        $created = $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $this->invoicePayload(
                $context,
                'field-confirm-denied-0001',
            ))
            ->assertCreated();

        $invoiceId = (int) $created->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices/'.$invoiceId.'/confirm')
            ->assertForbidden();

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoiceId,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $context['warehouse']->id,
            'product_id' => $context['product']->id,
            'quantity' => 18,
        ]);
    }

    public function test_field_user_can_create_payment_but_cannot_confirm_it(): void
    {
        $context = $this->context('A');
        $invoice = $this->confirmedInvoice(
            $context,
            'payment-source-invoice-0001',
        );
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );
        $token = $this->tokenFor($user);

        $payment = $this->withToken($token)
            ->postJson('/api/v1/operational/customer-payments', [
                'client_reference' => 'payment-0001',
                'customer_id' => $context['customer']->id,
                'sales_invoice_id' => $invoice->id,
                'payment_date' => today()->toDateString(),
                'payment_method' => 'cash',
                'amount' => 7,
                'notes' => 'Field payment',
            ])
            ->assertCreated();

        $paymentId = (int) $payment->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/operational/customer-payments/'.$paymentId.'/confirm')
            ->assertForbidden();

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('20.00', $invoice->remaining_amount);
        $this->assertDatabaseHas('customer_payments', [
            'id' => $paymentId,
            'status' => 'draft',
        ]);
    }

    public function test_scoped_user_cannot_link_payment_to_out_of_scope_invoice(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $outsideInvoice = $this->confirmedInvoice(
            $second,
            'outside-scope-invoice-0001',
        );
        $representative = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $first['representative'],
        );

        $this->withToken($this->tokenFor($representative))
            ->postJson('/api/v1/operational/customer-payments', [
                'client_reference' => 'outside-scope-payment-0001',
                'customer_id' => $first['customer']->id,
                'sales_invoice_id' => $outsideInvoice->id,
                'payment_date' => today()->toDateString(),
                'payment_method' => 'cash',
                'amount' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['sales_invoice_id']);
    }

    public function test_field_user_can_create_return_but_cannot_confirm_it(): void
    {
        $context = $this->context('A');
        $invoice = $this->confirmedInvoice(
            $context,
            'return-source-invoice-0001',
        );
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );
        $token = $this->tokenFor($user);

        $salesReturn = $this->withToken($token)
            ->postJson('/api/v1/operational/sales-returns', [
                'client_reference' => 'sales-return-0001',
                'customer_id' => $context['customer']->id,
                'sales_invoice_id' => $invoice->id,
                'vehicle_id' => $context['vehicle']->id,
                'route_id' => $context['route']->id,
                'warehouse_id' => $context['warehouse']->id,
                'sales_representative_id' => $context['representative']->id,
                'return_date' => today()->toDateString(),
                'return_reason' => 'damaged',
                'items' => [[
                    'product_id' => $context['product']->id,
                    'quantity' => 1,
                    'unit_price' => 10,
                ]],
            ])
            ->assertCreated();

        $returnId = (int) $salesReturn->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sales-returns/'.$returnId.'/confirm')
            ->assertForbidden();

        $this->assertDatabaseHas('sales_returns', [
            'id' => $returnId,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $context['warehouse']->id,
            'product_id' => $context['product']->id,
            'quantity' => 20,
        ]);
    }

    public function test_driver_can_submit_and_update_expense_but_cannot_approve_it(): void
    {
        $context = $this->context('A');
        $driver = $this->userForEmployee(
            User::ROLE_DRIVER,
            $context['driver'],
        );
        $driverToken = $this->tokenFor($driver);

        $created = $this->withToken($driverToken)
            ->postJson('/api/v1/operational/vehicle-expenses', [
                'client_reference' => 'driver-expense-0001',
                'expense_date' => today()->toDateString(),
                'vehicle_id' => $context['vehicle']->id,
                'warehouse_id' => $context['warehouse']->id,
                'route_id' => $context['route']->id,
                'driver_id' => $context['driver']->id,
                'expense_type' => 'fuel',
                'amount' => 15,
                'payment_method' => 'cash',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $expenseId = (int) $created->json('data.id');

        $this->withToken($driverToken)
            ->patchJson('/api/v1/operational/vehicle-expenses/'.$expenseId, [
                'amount' => 18,
            ])
            ->assertOk()
            ->assertJsonPath('data.amount', '18.00');

        $this->withToken($driverToken)
            ->postJson('/api/v1/operational/vehicle-expenses/'.$expenseId.'/approve')
            ->assertForbidden();

        $this->assertDatabaseHas('vehicle_expenses', [
            'id' => $expenseId,
            'status' => 'pending',
            'amount' => 18,
        ]);
    }

    public function test_representative_owns_scoped_vehicle_expense_with_mobile_sales_semantics(): void
    {
        $first = $this->context('EXP-REP-A');
        $second = $this->context('EXP-REP-B');
        $first['route']->update(['driver_id' => null]);
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $first['representative'],
        );
        $token = $this->tokenFor($user);
        $payload = [
            'client_reference' => 'representative-expense-0001',
            'expense_date' => today()->toDateString(),
            'vehicle_id' => $first['vehicle']->id,
            'warehouse_id' => $first['warehouse']->id,
            'route_id' => $first['route']->id,
            'expense_type' => 'fuel',
            'amount' => 15,
            'payment_method' => 'cash',
        ];

        $this->withToken($token)
            ->postJson('/api/v1/operational/vehicle-expenses', [
                ...$payload,
                'vehicle_id' => $second['vehicle']->id,
                'warehouse_id' => $second['warehouse']->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'field_context_mismatch');

        $created = $this->withToken($token)
            ->postJson('/api/v1/operational/vehicle-expenses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.sales_representative.id', $first['representative']->id)
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.operation_source', 'mobile_sales');

        $expenseId = (int) $created->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/operational/vehicle-expenses', $payload)
            ->assertOk()
            ->assertJsonPath('meta.idempotency.replayed', true)
            ->assertJsonPath('data.id', $expenseId);

        $this->assertDatabaseHas('vehicle_expenses', [
            'id' => $expenseId,
            'route_id' => $first['route']->id,
            'vehicle_id' => $first['vehicle']->id,
            'warehouse_id' => $first['warehouse']->id,
            'driver_id' => null,
            'sales_representative_id' => $first['representative']->id,
            'payment_method' => 'cash',
            'operation_source' => 'mobile_sales',
        ]);
    }

    public function test_field_user_cannot_create_daily_closing(): void
    {
        $context = $this->context('A');
        $user = $this->userForEmployee(
            User::ROLE_SALES_REPRESENTATIVE,
            $context['representative'],
        );

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/operational/daily-closings', [
                'client_reference' => 'daily-closing-0001',
                'closing_date' => today()->toDateString(),
                'vehicle_id' => $context['vehicle']->id,
                'route_id' => $context['route']->id,
                'warehouse_id' => $context['warehouse']->id,
                'sales_representative_id' => $context['representative']->id,
                'actual_cash_amount' => 0,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('daily_closings', [
            'client_reference' => 'daily-closing-0001',
        ]);
    }

    public function test_write_validation_uses_standard_api_envelope(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
        ]);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/operational/sales-invoices', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors([
                'client_reference',
                'customer_id',
                'warehouse_id',
                'invoice_date',
                'payment_type',
                'items',
            ]);
    }

    /** @return array<string, mixed> */
    private function context(string $suffix): array
    {
        $area = Area::query()->create([
            'code' => 'WRITE-AREA-'.$suffix,
            'name_ar' => 'منطقة '.$suffix,
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'WRITE-VEH-'.$suffix,
            'plate_number' => 'WRITE-PLATE-'.$suffix,
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'WRITE-WH-'.$suffix,
            'name' => 'مستودع '.$suffix,
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'WRITE-DRV-'.$suffix,
            'name' => 'سائق '.$suffix,
            'type' => 'driver',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'WRITE-REP-'.$suffix,
            'name' => 'مندوب '.$suffix,
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'WRITE-ROUTE-'.$suffix,
            'name' => 'خط '.$suffix,
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'code' => 'WRITE-CUS-'.$suffix,
            'name' => 'عميل '.$suffix,
            'area_id' => $area->id,
            'route_id' => $route->id,
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'WRITE-CAT-'.$suffix,
            'name_ar' => 'تصنيف '.$suffix,
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'WRITE-UNIT-'.$suffix,
            'name_ar' => 'وحدة '.$suffix,
            'symbol' => 'U',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'WRITE-SKU-'.$suffix,
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

        return compact(
            'area',
            'vehicle',
            'warehouse',
            'driver',
            'representative',
            'route',
            'customer',
            'product',
        );
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function invoicePayload(
        array $context,
        string $clientReference,
        string $paymentType = 'cash',
    ): array {
        return [
            'client_reference' => $clientReference,
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'invoice_date' => today()->toDateString(),
            'payment_type' => $paymentType,
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

    /** @param array<string, mixed> $context */
    private function confirmedInvoice(
        array $context,
        string $clientReference,
    ): SalesInvoice {
        return SalesInvoice::query()->create([
            'invoice_number' => 'TEST-'.$clientReference,
            'client_reference' => $clientReference,
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'invoice_date' => today()->toDateString(),
            'status' => 'confirmed',
            'payment_type' => 'credit',
            'subtotal' => 20,
            'total_amount' => 20,
            'paid_amount' => 0,
            'remaining_amount' => 20,
            'invoice_cash_amount' => 0,
        ]);
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
            'write-api-test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;
    }
}
