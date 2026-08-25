<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RepresentativeInvoiceImmediateDeliveryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('paymentCases')]
    public function test_representative_invoice_confirms_immediately(
        string $paymentType,
        float $submittedPaid,
        string $expectedPaid,
        string $expectedRemaining,
    ): void {
        $context = $this->context(strtoupper($paymentType));
        $invoice = $this->invoice($context, $paymentType, $submittedPaid, 2);

        $confirmed = app(SalesInvoiceService::class)->confirm($invoice);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame($expectedPaid, $confirmed->paid_amount);
        $this->assertSame($expectedPaid, $confirmed->invoice_cash_amount);
        $this->assertSame($expectedRemaining, $confirmed->remaining_amount);
        $this->assertEqualsWithDelta(38, $this->stockQuantity($context), 0.0001);
    }

    public static function paymentCases(): array
    {
        return [
            'cash' => ['cash', 0, '20.00', '0.00'],
            'credit' => ['credit', 9, '0.00', '20.00'],
            'partial' => ['partial', 5, '5.00', '15.00'],
        ];
    }

    public function test_representative_invoice_cancellation_restores_stock(): void
    {
        $context = $this->context('CANCEL');
        $invoice = app(SalesInvoiceService::class)->confirm(
            $this->invoice($context, 'credit', 0, 2),
        );

        $cancelled = app(SalesInvoiceService::class)->cancel(
            $invoice,
            'إلغاء فاتورة المندوب وإعادة المخزون.',
        );

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertEqualsWithDelta(40, $this->stockQuantity($context), 0.0001);
    }

    /** @return array<string, mixed> */
    private function context(string $suffix): array
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
        $representative = Employee::query()->create([
            'employee_code' => 'R1A-REP-'.$suffix,
            'name' => 'مندوب '.$suffix,
            'type' => User::ROLE_SALES_REPRESENTATIVE,
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
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
            'quantity' => 40,
            'average_unit_cost' => 5,
        ]);
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->syncRoles([User::ROLE_SALES_REPRESENTATIVE]);
        $representative->update(['user_id' => $user->id]);
        $this->actingAs($user);

        return compact('vehicle', 'warehouse', 'representative', 'route', 'customer', 'product');
    }

    /** @param array<string, mixed> $context */
    private function invoice(
        array $context,
        string $paymentType,
        float $paidAmount,
        float $quantity,
    ): SalesInvoice {
        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'R1A-INV-'.$context['route']->id,
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
    private function stockQuantity(array $context): float
    {
        return (float) StockBalance::query()
            ->where('warehouse_id', $context['warehouse']->id)
            ->where('product_id', $context['product']->id)
            ->where('batch_key', '')
            ->where('expiry_key', '')
            ->value('quantity');
    }
}
