<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class CustomerCreditControlTest extends TestCase
{
    use DatabaseTransactions;

    public function test_credit_invoice_uses_customer_due_terms(): void
    {
        $customer = $this->customer(creditLimit: 0, creditDays: 45);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-CREDIT-TERMS-'.uniqid(),
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse()->id,
            'invoice_date' => '2026-07-01',
            'payment_type' => 'credit',
            'status' => 'draft',
        ]);

        $this->assertSame('2026-08-15', $invoice->due_date?->toDateString());

        $cashInvoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-CASH-TERMS-'.uniqid(),
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse()->id,
            'invoice_date' => '2026-07-02',
            'due_date' => '2026-09-01',
            'payment_type' => 'cash',
            'status' => 'draft',
        ]);

        $this->assertSame('2026-07-02', $cashInvoice->due_date?->toDateString());
    }

    public function test_credit_limit_blocks_confirmation_without_approved_override(): void
    {
        $context = $this->invoiceContext(creditLimit: 50);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $this->actingAs($manager);

        try {
            app(SalesInvoiceService::class)->confirm($context['invoice']);
            $this->fail('Expected credit limit confirmation failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('حد الائتمان', $exception->getMessage());
        }

        $this->assertSame('draft', $context['invoice']->refresh()->status);
        $this->assertSame('20.000', $context['warehouse']->stockBalances()->firstOrFail()->quantity);
    }

    public function test_supervisor_cannot_override_customer_credit_limit(): void
    {
        $context = $this->invoiceContext(creditLimit: 50);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $area = Area::query()->create([
            'code' => 'CR-A-'.uniqid(),
            'name_ar' => 'Credit scope',
            'status' => 'active',
        ]);

        $context['invoice']->customer()->update(['area_id' => $area->id]);
        $supervisor->accessAreas()->sync([$area->id]);
        $supervisor->accessWarehouses()->sync([$context['warehouse']->id]);

        $this->actingAs($supervisor);

        $context['invoice']->forceFill([
            'credit_limit_override_requested' => true,
            'credit_limit_override_reason' => 'استثناء اختباري موثق للعميل',
        ])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('لا تملك صلاحية تجاوز حد ائتمان العميل.');

        app(SalesInvoiceService::class)->confirm($context['invoice']);
    }

    public function test_manager_override_is_audited_when_limit_is_exceeded(): void
    {
        $context = $this->invoiceContext(creditLimit: 50);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $this->actingAs($manager);

        $context['invoice']->forceFill([
            'credit_limit_override_requested' => true,
            'credit_limit_override_reason' => 'موافقة إدارية استثنائية لاختبار حد الائتمان',
        ])->save();

        $invoice = app(SalesInvoiceService::class)->confirm($context['invoice']);

        $this->assertSame('confirmed', $invoice->status);
        $this->assertTrue($invoice->credit_limit_overridden);
        $this->assertSame($manager->id, $invoice->credit_limit_overridden_by);
        $this->assertSame('50.00', $invoice->credit_limit_snapshot);
        $this->assertSame('0.00', $invoice->credit_exposure_before);
        $this->assertSame('100.00', $invoice->credit_exposure_after);
        $this->assertNotNull($invoice->credit_limit_overridden_at);
        $this->assertSame('15.000', $context['warehouse']->stockBalances()->firstOrFail()->quantity);
    }

    /** @return array{invoice: SalesInvoice, warehouse: Warehouse} */
    private function invoiceContext(float $creditLimit): array
    {
        $suffix = uniqid();
        $date = '2026-07-10';
        $customer = $this->customer($creditLimit, 30);
        $warehouse = $this->warehouse();
        $product = Product::query()->create([
            'sku' => 'CR-P-'.$suffix,
            'name_ar' => 'منتج ائتماني '.$suffix,
            'purchase_price' => 10,
            'sale_price' => 20,
            'status' => 'active',
        ]);

        app(InventoryMovementService::class)->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 20,
            unitCost: 10,
            movementType: 'opening_balance',
            movementDate: $date,
        );

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-CREDIT-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => $date,
            'payment_type' => 'credit',
            'status' => 'draft',
            'paid_amount' => 0,
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 20,
            'discount_amount' => 0,
        ]);

        return [
            'invoice' => $invoice->refresh(),
            'warehouse' => $warehouse,
        ];
    }

    private function customer(float $creditLimit, int $creditDays): Customer
    {
        $suffix = uniqid();

        return Customer::query()->create([
            'code' => 'CR-C-'.$suffix,
            'name' => 'عميل ائتماني '.$suffix,
            'credit_limit' => $creditLimit,
            'credit_days' => $creditDays,
            'payment_type' => 'credit',
            'status' => 'active',
        ]);
    }

    private function warehouse(): Warehouse
    {
        $suffix = uniqid();

        return Warehouse::query()->create([
            'code' => 'CR-W-'.$suffix,
            'name' => 'مستودع ائتماني '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);
    }
}
