<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\DriverDelivery;
use App\Models\DriverJourney;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RepresentativeInvoiceImmediateDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_driver_journey_creation_preserves_numbering_and_relationships(): void
    {
        $firstContext = $this->context('NUMBER-A', 40);
        $first = $this->legacyJourney($firstContext, 'ready');

        $this->assertSame('JRN-'.today()->format('Ymd').'-00001', $first->journey_number);
        $this->assertTrue($first->route->is($firstContext['route']));
        $this->assertTrue($first->vehicle->is($firstContext['vehicle']));
        $this->assertTrue($first->warehouse->is($firstContext['warehouse']));
        $this->assertTrue($first->driver->is($firstContext['driver']));
        $this->assertTrue(
            $first->salesRepresentative->is($firstContext['representative']),
        );

        $this->app['auth']->forgetGuards();
        $secondContext = $this->context('NUMBER-B', 40);
        $second = $this->legacyJourney($secondContext, 'completed');

        $this->assertSame('JRN-'.today()->format('Ymd').'-00002', $second->journey_number);
    }

    public function test_cash_invoice_confirms_without_driver_journey_or_delivery(): void
    {
        $context = $this->context('CASH', 40);
        $invoice = $this->invoice($context, 'cash', 0, 2);

        $confirmed = app(SalesInvoiceService::class)->confirm($invoice);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame('20.00', $confirmed->paid_amount);
        $this->assertSame('20.00', $confirmed->invoice_cash_amount);
        $this->assertSame('0.00', $confirmed->remaining_amount);
        $this->assertEqualsWithDelta(38, $this->stockQuantity($context), 0.0001);
        $this->assertDatabaseCount('driver_journeys', 0);
        $this->assertDeliveryFree($confirmed);
    }

    public function test_credit_invoice_confirms_without_driver_journey_or_delivery(): void
    {
        $context = $this->context('CREDIT', 40);
        $invoice = $this->invoice($context, 'credit', 9, 2);

        $confirmed = app(SalesInvoiceService::class)->confirm($invoice);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame('0.00', $confirmed->paid_amount);
        $this->assertSame('0.00', $confirmed->invoice_cash_amount);
        $this->assertSame('20.00', $confirmed->remaining_amount);
        $this->assertEqualsWithDelta(38, $this->stockQuantity($context), 0.0001);
        $this->assertDatabaseCount('driver_journeys', 0);
        $this->assertDeliveryFree($confirmed);
    }

    public function test_partial_invoice_confirms_without_driver_journey_or_delivery(): void
    {
        $context = $this->context('PARTIAL', 40);
        $invoice = $this->invoice($context, 'partial', 5, 2);

        $confirmed = app(SalesInvoiceService::class)->confirm($invoice);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame('5.00', $confirmed->paid_amount);
        $this->assertSame('5.00', $confirmed->invoice_cash_amount);
        $this->assertSame('15.00', $confirmed->remaining_amount);
        $this->assertEqualsWithDelta(38, $this->stockQuantity($context), 0.0001);
        $this->assertDatabaseCount('driver_journeys', 0);
        $this->assertDeliveryFree($confirmed);
    }

    public function test_multiple_confirmed_representative_invoices_remain_delivery_free(): void
    {
        $context = $this->context('MULTIPLE', 40);
        $first = $this->invoice($context, 'cash', 0, 2, 'A');
        $second = $this->invoice($context, 'credit', 0, 3, 'B');

        app(SalesInvoiceService::class)->confirm($first);
        app(SalesInvoiceService::class)->confirm($second);

        $this->assertEqualsWithDelta(35, $this->stockQuantity($context), 0.0001);
        $this->assertDatabaseCount('driver_deliveries', 0);
        $this->assertDatabaseCount('driver_delivery_items', 0);
    }

    public function test_invoice_cancellation_without_legacy_delivery_restores_stock(): void
    {
        $context = $this->context('CANCEL-NONE', 40);
        $invoice = app(SalesInvoiceService::class)->confirm(
            $this->invoice($context, 'credit', 0, 2),
        );

        $cancelled = app(SalesInvoiceService::class)->cancel(
            $invoice,
            'إلغاء فاتورة فورية دون تسليم سائق قديم.',
        );

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertEqualsWithDelta(40, $this->stockQuantity($context), 0.0001);
        $this->assertDatabaseCount('driver_deliveries', 0);
        $this->assertDatabaseCount('driver_delivery_items', 0);
    }

    public function test_cancellation_reconciles_pending_legacy_delivery_on_ready_journey(): void
    {
        $context = $this->context('CANCEL-READY', 40);
        $invoice = app(SalesInvoiceService::class)->confirm(
            $this->invoice($context, 'credit', 0, 2),
        );
        $journey = $this->legacyJourney($context, 'ready');
        $delivery = $this->legacyDelivery($context, $journey, $invoice, 'pending');

        $cancelled = app(SalesInvoiceService::class)->cancel(
            $invoice,
            'إلغاء فاتورة مرتبطة بتسليم سائق قديم جاهز.',
        );

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertDatabaseMissing('driver_deliveries', ['id' => $delivery->id]);
        $this->assertDatabaseCount('driver_delivery_items', 0);
        $this->assertDatabaseHas('driver_journeys', [
            'id' => $journey->id,
            'status' => 'ready',
        ]);
        $this->assertEqualsWithDelta(40, $this->stockQuantity($context), 0.0001);
    }

    public function test_cancellation_is_blocked_for_progressed_legacy_delivery(): void
    {
        $context = $this->context('CANCEL-PROGRESSED', 40);
        $invoice = app(SalesInvoiceService::class)->confirm(
            $this->invoice($context, 'credit', 0, 2),
        );
        $journey = $this->legacyJourney($context, 'in_progress');
        $delivery = $this->legacyDelivery($context, $journey, $invoice, 'delivered');

        try {
            app(SalesInvoiceService::class)->cancel(
                $invoice,
                'محاولة إلغاء فاتورة بعد تقدم التسليم القديم.',
            );
            $this->fail('Progressed legacy delivery must block invoice cancellation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('بعد بدء رحلة السائق', $exception->getMessage());
        }

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoice->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('driver_deliveries', [
            'id' => $delivery->id,
            'status' => 'delivered',
        ]);
        $this->assertDatabaseCount('driver_delivery_items', 1);
        $this->assertEqualsWithDelta(38, $this->stockQuantity($context), 0.0001);
    }

    /** @return array<string, mixed> */
    private function context(string $suffix, float $stock): array
    {
        $area = Area::query()->create([
            'code' => 'R1A-AREA-'.$suffix,
            'name_ar' => 'منطقة '.$suffix,
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'R1A-VEH-'.$suffix,
            'plate_number' => 'R1A-PLATE-'.$suffix,
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'R1A-WH-'.$suffix,
            'name' => 'مستودع '.$suffix,
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'R1A-DRV-'.$suffix,
            'name' => 'سائق '.$suffix,
            'type' => User::ROLE_DRIVER,
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'R1A-REP-'.$suffix,
            'name' => 'مندوب '.$suffix,
            'type' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'R1A-ROUTE-'.$suffix,
            'name' => 'خط '.$suffix,
            'visit_days' => [],
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'code' => 'R1A-CUS-'.$suffix,
            'name' => 'عميل '.$suffix,
            'area_id' => $area->id,
            'route_id' => $route->id,
            'credit_limit' => 100000,
            'credit_days' => 30,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'R1A-SKU-'.$suffix,
            'name_ar' => 'منتج '.$suffix,
            'purchase_price' => 5,
            'sale_price' => 10,
            'wholesale_price' => 9,
            'status' => 'active',
        ]);
        StockBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_key' => '',
            'expiry_key' => '',
            'quantity' => $stock,
            'average_unit_cost' => 5,
        ]);
        $user = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->syncRoles([User::ROLE_SALES_REPRESENTATIVE]);
        $representative->update(['user_id' => $user->id]);
        $this->actingAs($user);

        return compact(
            'vehicle',
            'warehouse',
            'driver',
            'representative',
            'route',
            'customer',
            'product',
        );
    }

    /** @param array<string, mixed> $context */
    private function invoice(
        array $context,
        string $paymentType,
        float $paidAmount,
        float $quantity,
        string $suffix = 'A',
    ): SalesInvoice {
        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'R1A-INV-'.$context['route']->id.'-'.$suffix,
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'invoice_date' => today(),
            'status' => 'draft',
            'payment_type' => $paymentType,
            'paid_amount' => $paidAmount,
        ]);
        $invoice->items()->create([
            'product_id' => $context['product']->id,
            'quantity' => $quantity,
            'unit_price' => 10,
            'discount_amount' => 0,
        ]);

        return $invoice->refresh();
    }

    /** @param array<string, mixed> $context */
    private function legacyJourney(array $context, string $status): DriverJourney
    {
        return DriverJourney::query()->create([
            'journey_date' => today(),
            'route_id' => $context['route']->id,
            'vehicle_id' => $context['vehicle']->id,
            'warehouse_id' => $context['warehouse']->id,
            'driver_id' => $context['driver']->id,
            'sales_representative_id' => $context['representative']->id,
            'status' => $status,
            'started_at' => $status === 'in_progress' ? now() : null,
        ]);
    }

    /** @param array<string, mixed> $context */
    private function legacyDelivery(
        array $context,
        DriverJourney $journey,
        SalesInvoice $invoice,
        string $status,
    ): DriverDelivery {
        $item = $invoice->items()->firstOrFail();
        $delivery = DriverDelivery::query()->create([
            'driver_journey_id' => $journey->id,
            'sales_invoice_id' => $invoice->id,
            'customer_id' => $context['customer']->id,
            'route_id' => $context['route']->id,
            'vehicle_id' => $context['vehicle']->id,
            'warehouse_id' => $context['warehouse']->id,
            'driver_id' => $context['driver']->id,
            'sales_representative_id' => $context['representative']->id,
            'status' => $status,
            'expected_quantity' => $item->quantity,
            'delivered_quantity' => $status === 'delivered' ? $item->quantity : 0,
        ]);
        $delivery->items()->create([
            'sales_invoice_item_id' => $item->id,
            'product_id' => $item->product_id,
            'batch_number' => $item->batch_number,
            'expiry_date' => $item->expiry_date,
            'expected_quantity' => $item->quantity,
            'delivered_quantity' => $status === 'delivered' ? $item->quantity : 0,
        ]);

        return $delivery;
    }

    /** @param array<string, mixed> $context */
    private function stockQuantity(array $context): float
    {
        return (float) StockBalance::query()
            ->where('warehouse_id', $context['warehouse']->id)
            ->where('product_id', $context['product']->id)
            ->where('batch_key', '')
            ->where('expiry_key', '')
            ->value('quantity');
    }

    private function assertDeliveryFree(SalesInvoice $invoice): void
    {
        $this->assertDatabaseMissing('driver_deliveries', [
            'sales_invoice_id' => $invoice->id,
        ]);
        $this->assertDatabaseCount('driver_delivery_items', 0);
    }
}
