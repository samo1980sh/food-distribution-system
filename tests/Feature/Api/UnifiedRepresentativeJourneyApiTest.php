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
use App\Services\Sales\CustomerFinancialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnifiedRepresentativeJourneyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_runs_the_unified_field_day(): void
    {
        $context = $this->context('PRIMARY', 1);
        $user = $this->representativeUser($context['representative']);
        $token = $this->tokenFor($user);
        $load = $this->pendingLoad($context);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.route.id', $context['route']->id)
            ->assertJsonPath('data.vehicle.id', $context['vehicle']->id)
            ->assertJsonPath('data.warehouse.id', $context['warehouse']->id);

        $journeyId = (int) $opened->json('data.id');
        $visitId = (int) $opened->json('data.visits.0.id');

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
            'inventory_submitted_by' => $user->id,
            'cash_submitted_by' => $user->id,
        ]);
    }

    public function test_representative_runs_a_complete_unified_day_end_to_end(): void
    {
        $context = $this->context('COMPLETE-DAY', 1);
        $customer = Customer::query()
            ->where('route_id', $context['route']->id)
            ->firstOrFail();
        $customer->update([
            'credit_limit' => 1000,
            'credit_days' => 30,
            'payment_type' => 'credit',
        ]);
        StockBalance::query()
            ->where('warehouse_id', $context['warehouse']->id)
            ->where('product_id', $context['product']->id)
            ->update(['quantity' => 100]);

        $batchProduct = Product::query()->create([
            'sku' => 'UNIFIED-SKU-COMPLETE-DAY-BATCHED',
            'name_ar' => 'Complete day batched product',
            'category_id' => $context['product']->category_id,
            'unit_id' => $context['product']->unit_id,
            'purchase_price' => 5,
            'sale_price' => 10,
            'wholesale_price' => 9,
            'status' => 'active',
        ]);
        foreach (['BATCH-A', 'BATCH-B'] as $batchNumber) {
            StockBalance::query()->create([
                'warehouse_id' => $context['warehouse']->id,
                'product_id' => $batchProduct->id,
                'batch_number' => $batchNumber,
                'batch_key' => $batchNumber,
                'expiry_key' => '',
                'quantity' => 20,
                'average_unit_cost' => 5,
            ]);
        }

        $user = $this->representativeUser($context['representative']);
        $load = $this->pendingLoad($context);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'unified-complete-day-device',
            'device_name' => 'Unified complete day test',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ])->assertOk()
            ->assertJsonPath('data.bootstrap.user.role', User::ROLE_SALES_REPRESENTATIVE);
        $token = (string) $login->json('data.token');

        $this->withFreshToken($token)
            ->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.field_workspace.role', User::ROLE_SALES_REPRESENTATIVE)
            ->assertJsonPath('data.field_workspace.unified', true)
            ->assertJsonPath('data.field_workspace.legacy', false)
            ->assertJsonPath('data.sync.registry_version', 8);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonCount(1, 'data.visits');
        $journeyId = (int) $opened->json('data.id');
        $visitId = (int) $opened->json('data.visits.0.id');

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
            ->assertJsonPath('data.handover_status', 'received')
            ->assertJsonPath('data.items.0.received_quantity', '10.000');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $baseInvoice = [
            'sales_visit_id' => $visitId,
            'customer_id' => $customer->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'invoice_date' => today()->toDateString(),
            'discount_amount' => 0,
            'tax_amount' => 0,
        ];
        $standardItem = [[
            'product_id' => $context['product']->id,
            'quantity' => 2,
            'unit_price' => 10,
            'discount_amount' => 0,
        ]];

        $cashPayload = [
            ...$baseInvoice,
            'client_reference' => 'unified-complete-cash',
            'payment_type' => 'cash',
            'items' => $standardItem,
        ];
        $cashInvoice = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $cashPayload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonPath('data.paid_amount', '20.00')
            ->assertJsonPath('data.remaining_amount', '0.00');
        $cashInvoiceId = (int) $cashInvoice->json('data.id');

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-invoices', $cashPayload)
            ->assertOk()
            ->assertJsonPath('data.id', $cashInvoiceId)
            ->assertJsonPath('meta.idempotency.replayed', true);

        $creditInvoice = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-invoices', [
                ...$baseInvoice,
                'client_reference' => 'unified-complete-credit',
                'payment_type' => 'credit',
                'paid_amount' => 0,
                'items' => [[...$standardItem[0], 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total_amount', '10.00')
            ->assertJsonPath('data.paid_amount', '0.00')
            ->assertJsonPath('data.remaining_amount', '10.00');
        $creditInvoiceId = (int) $creditInvoice->json('data.id');

        $partialInvoice = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-invoices', [
                ...$baseInvoice,
                'client_reference' => 'unified-complete-partial',
                'payment_type' => 'partial',
                'paid_amount' => 4,
                'items' => [[...$standardItem[0], 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total_amount', '10.00')
            ->assertJsonPath('data.paid_amount', '4.00')
            ->assertJsonPath('data.remaining_amount', '6.00');

        $multiBatchInvoice = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-invoices', [
                ...$baseInvoice,
                'client_reference' => 'unified-complete-multi-batch',
                'payment_type' => 'cash',
                'items' => [
                    [
                        'product_id' => $batchProduct->id,
                        'batch_number' => 'BATCH-A',
                        'quantity' => 20,
                        'unit_price' => 10,
                        'discount_amount' => 0,
                    ],
                    [
                        'product_id' => $batchProduct->id,
                        'batch_number' => 'BATCH-B',
                        'quantity' => 20,
                        'unit_price' => 10,
                        'discount_amount' => 0,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total_amount', '400.00')
            ->assertJsonCount(2, 'data.items');
        $multiBatchInvoiceId = (int) $multiBatchInvoice->json('data.id');

        $invoiceList = $this->withFreshToken($token)
            ->getJson('/api/v1/operational/sales-invoices?customer_id='.$customer->id.'&status=confirmed')
            ->assertOk()
            ->assertJsonCount(4, 'data.items');
        $this->assertEqualsCanonicalizing(
            [$cashInvoiceId, $creditInvoiceId, (int) $partialInvoice->json('data.id'), $multiBatchInvoiceId],
            collect($invoiceList->json('data.items'))->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $payment = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/customer-payments', [
                'client_reference' => 'unified-complete-payment',
                'sales_visit_id' => $visitId,
                'customer_id' => $customer->id,
                'sales_invoice_id' => $creditInvoiceId,
                'vehicle_id' => $context['vehicle']->id,
                'route_id' => $context['route']->id,
                'warehouse_id' => $context['warehouse']->id,
                'sales_representative_id' => $context['representative']->id,
                'payment_date' => today()->toDateString(),
                'payment_method' => 'cash',
                'amount' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');

        $salesReturn = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-returns', [
                'client_reference' => 'unified-complete-return',
                'sales_visit_id' => $visitId,
                'customer_id' => $customer->id,
                'sales_invoice_id' => $cashInvoiceId,
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
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $expense = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/vehicle-expenses', [
                'client_reference' => 'unified-complete-expense',
                'expense_date' => today()->toDateString(),
                'route_id' => $context['route']->id,
                'vehicle_id' => $context['vehicle']->id,
                'warehouse_id' => $context['warehouse']->id,
                'expense_type' => 'fuel',
                'amount' => 15,
                'payment_method' => 'cash',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sales_representative.id', $context['representative']->id)
            ->assertJsonPath('data.expense_type', 'fuel')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.operation_source', 'mobile_sales');

        $this->withFreshToken($token)
            ->getJson('/api/v1/operational/today')
            ->assertOk()
            ->assertJsonPath('data.contexts.sales_representative.summary.journey.id', $journeyId)
            ->assertJsonPath('data.contexts.sales_representative.summary.journey.status', 'in_progress')
            ->assertJsonPath('data.contexts.sales_representative.summary.load_custody.status', 'received');

        $closing = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/daily-closings/open-today', [
                'route_id' => $context['route']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sales_representative.id', $context['representative']->id)
            ->assertJsonPath('data.expected_cash_amount', '412.00');
        $closingId = (int) $closing->json('data.id');
        $inventoryItems = collect($closing->json('data.items'))
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product']['id'],
                'actual_quantity' => (float) $item['expected_quantity'],
            ])
            ->values()
            ->all();

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/daily-closings/{$closingId}/submit-inventory", [
                'items' => $inventoryItems,
            ])
            ->assertConflict();
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/finish")
            ->assertConflict()
            ->assertJsonPath('code', 'sales_visits_pending');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/complete", [
                'outcome' => 'invoice_created',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.documents.invoices', 4)
            ->assertJsonPath('data.documents.payments', 1)
            ->assertJsonPath('data.documents.returns', 1);
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/finish")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $varianceItems = $inventoryItems;
        $varianceItems[0]['actual_quantity']++;
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/daily-closings/{$closingId}/submit-inventory", [
                'items' => $varianceItems,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'business_rule_violation');
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/daily-closings/{$closingId}/submit-inventory", [
                'items' => $inventoryItems,
            ])
            ->assertOk()
            ->assertJsonPath('data.field_handover.inventory.submitted', true)
            ->assertJsonPath('data.field_handover.complete', false);

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/daily-closings/{$closingId}/submit-cash", [
                'actual_cash_amount' => 413,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'business_rule_violation');
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/daily-closings/{$closingId}/submit-cash", [
                'actual_cash_amount' => 412,
            ])
            ->assertOk()
            ->assertJsonPath('data.expected_cash_amount', '412.00')
            ->assertJsonPath('data.actual_cash_amount', '412.00')
            ->assertJsonPath('data.cash_difference', '0.00')
            ->assertJsonPath('data.field_handover.complete', true)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.workflow_status', 'closed')
            ->assertJsonPath('data.requires_admin_review', false);

        $this->assertDatabaseHas('daily_closings', [
            'id' => $closingId,
            'sales_representative_id' => $context['representative']->id,
            'inventory_submitted_by' => $user->id,
            'cash_submitted_by' => $user->id,
            'expected_cash_amount' => 412,
            'actual_cash_amount' => 412,
            'status' => 'confirmed',
            'confirmed_by' => $user->id,
        ]);
        $this->assertDatabaseHas('customer_payments', [
            'id' => (int) $payment->json('data.id'),
            'status' => 'confirmed',
            'confirmed_by' => $user->id,
        ]);
        $this->assertDatabaseHas('sales_returns', [
            'id' => (int) $salesReturn->json('data.id'),
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('vehicle_expenses', [
            'id' => (int) $expense->json('data.id'),
            'sales_representative_id' => $context['representative']->id,
            'expense_type' => 'fuel',
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $context['warehouse']->id,
            'product_id' => $context['product']->id,
            'quantity' => 96,
        ]);
        foreach (['BATCH-A', 'BATCH-B'] as $batchNumber) {
            $this->assertDatabaseHas('stock_balances', [
                'warehouse_id' => $context['warehouse']->id,
                'product_id' => $batchProduct->id,
                'batch_key' => $batchNumber,
                'quantity' => 0,
            ]);
            $this->assertDatabaseHas('sales_invoice_items', [
                'sales_invoice_id' => $multiBatchInvoiceId,
                'product_id' => $batchProduct->id,
                'batch_number' => $batchNumber,
                'quantity' => 20,
            ]);
        }
        $invoiceIds = [$cashInvoiceId, $creditInvoiceId, (int) $partialInvoice->json('data.id'), $multiBatchInvoiceId];
        $this->assertSame(5, DB::table('stock_movements')
            ->where('movement_type', 'sales_invoice')
            ->whereIn('reference_id', $invoiceIds)
            ->count());
        $this->assertSame(0, StockBalance::query()->where('quantity', '<', 0)->count());
        $this->assertSame(13.0, app(CustomerFinancialService::class)->customerBalance($customer));
        $this->assertDatabaseCount('sales_invoices', 4);
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
