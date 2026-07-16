<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
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
            ->assertJsonPath('data.write.enabled', true)
            ->assertJsonPath('data.write.idempotent_create', true)
            ->assertJsonPath('data.write.sales_invoices.create', true)
            ->assertJsonPath('data.write.sales_invoices.update', true)
            ->assertJsonPath('data.write.sales_invoices.confirm', false)
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
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonPath('meta.idempotency.replayed', false);

        $invoiceId = (int) $created->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $invoiceId)
            ->assertJsonPath('meta.idempotency.replayed', true);

        $this->assertDatabaseCount('sales_invoices', 1);

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

    public function test_manager_can_update_and_delete_a_draft_invoice(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager);

        $response = $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $this->invoicePayload(
                $context,
                'manager-invoice-0001',
            ))
            ->assertCreated();

        $invoiceId = (int) $response->json('data.id');

        $this->withToken($token)
            ->patchJson('/api/v1/operational/sales-invoices/'.$invoiceId, [
                'notes' => 'تم التعديل من التطبيق',
                'items' => [[
                    'product_id' => $context['product']->id,
                    'quantity' => 3,
                    'unit_price' => 10,
                    'discount_amount' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_amount', '30.00')
            ->assertJsonPath('data.notes', 'تم التعديل من التطبيق');

        $this->withToken($token)
            ->deleteJson('/api/v1/operational/sales-invoices/'.$invoiceId)
            ->assertOk()
            ->assertJsonPath('data.id', $invoiceId);

        $this->assertDatabaseMissing('sales_invoices', ['id' => $invoiceId]);
    }

    public function test_supervisor_can_confirm_invoice_and_inventory_service_is_reused(): void
    {
        $context = $this->context('A');
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $supervisor->accessRoutes()->sync([$context['route']->id]);
        $token = $this->tokenFor($supervisor);

        $created = $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $this->invoicePayload(
                $context,
                'supervisor-invoice-0001',
            ))
            ->assertCreated();

        $invoiceId = (int) $created->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices/'.$invoiceId.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.paid_amount', '20.00')
            ->assertJsonPath('data.remaining_amount', '0.00');

        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $context['warehouse']->id,
            'product_id' => $context['product']->id,
            'quantity' => 18,
        ]);
    }

    public function test_customer_payment_confirmation_updates_credit_invoice_balance(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager);

        $invoice = $this->createAndConfirmInvoice(
            $token,
            $context,
            'credit-invoice-0001',
            'credit',
        );

        $payment = $this->withToken($token)
            ->postJson('/api/v1/operational/customer-payments', [
                'client_reference' => 'payment-0001',
                'customer_id' => $context['customer']->id,
                'sales_invoice_id' => $invoice->id,
                'payment_date' => today()->toDateString(),
                'payment_method' => 'cash',
                'amount' => 7,
                'notes' => 'دفعة من التطبيق',
            ])
            ->assertCreated();

        $paymentId = (int) $payment->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/operational/customer-payments/'.$paymentId.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $invoice->refresh();
        $this->assertSame('7.00', $invoice->paid_amount);
        $this->assertSame('13.00', $invoice->remaining_amount);
    }

    public function test_scoped_user_cannot_link_payment_to_out_of_scope_invoice(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $outsideInvoice = $this->createAndConfirmInvoice(
            $this->tokenFor($manager),
            $second,
            'outside-scope-invoice-0001',
            'credit',
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

    public function test_sales_return_confirmation_restores_stock(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager);
        $invoice = $this->createAndConfirmInvoice(
            $token,
            $context,
            'return-source-invoice-0001',
            'credit',
        );

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
                'return_reason' => 'تالف',
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
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $context['warehouse']->id,
            'product_id' => $context['product']->id,
            'quantity' => 19,
        ]);
    }

    public function test_driver_can_submit_expense_and_supervisor_can_approve_it(): void
    {
        $context = $this->context('A');
        $driver = $this->userForEmployee(User::ROLE_DRIVER, $context['driver']);
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

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $supervisor->accessRoutes()->sync([$context['route']->id]);

        $this->withToken($this->tokenFor($supervisor))
            ->postJson('/api/v1/operational/vehicle-expenses/'.$expenseId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_daily_closing_create_update_refresh_and_confirm(): void
    {
        $context = $this->context('A');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $token = $this->tokenFor($manager);

        $created = $this->withToken($token)
            ->postJson('/api/v1/operational/daily-closings', [
                'client_reference' => 'daily-closing-0001',
                'closing_date' => today()->toDateString(),
                'vehicle_id' => $context['vehicle']->id,
                'route_id' => $context['route']->id,
                'warehouse_id' => $context['warehouse']->id,
                'sales_representative_id' => $context['representative']->id,
                'actual_cash_amount' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $closingId = (int) $created->json('data.id');

        $this->withToken($token)
            ->patchJson('/api/v1/operational/daily-closings/'.$closingId, [
                'actual_cash_amount' => 25,
                'notes' => 'جرد التطبيق',
            ])
            ->assertOk()
            ->assertJsonPath('data.actual_cash_amount', '25.00')
            ->assertJsonPath('data.notes', 'جرد التطبيق');

        $this->withToken($token)
            ->postJson('/api/v1/operational/daily-closings/'.$closingId.'/refresh-totals')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/operational/daily-closings/'.$closingId.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_write_validation_uses_standard_api_envelope(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);

        $this->withToken($this->tokenFor($manager))
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
     *  @return array<string, mixed>
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
    private function createAndConfirmInvoice(
        string $token,
        array $context,
        string $clientReference,
        string $paymentType,
    ): SalesInvoice {
        $created = $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $this->invoicePayload(
                $context,
                $clientReference,
                $paymentType,
            ))
            ->assertCreated();

        $invoiceId = (int) $created->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/operational/sales-invoices/'.$invoiceId.'/confirm')
            ->assertOk();

        return SalesInvoice::query()->findOrFail($invoiceId);
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
