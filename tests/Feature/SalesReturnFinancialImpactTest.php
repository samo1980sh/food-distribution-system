<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Warehouse;
use App\Services\Sales\CustomerFinancialService;
use App\Services\Sales\SalesReturnService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesReturnFinancialImpactTest extends TestCase
{
    use DatabaseTransactions;

    public function test_confirmed_return_reduces_credit_invoice_remaining_amount(): void
    {
        [$invoice, $product] = $this->createConfirmedCreditInvoice();

        $salesReturn = $this->createSalesReturn($invoice, $product, quantity: 2);
        $this->prepareStockMovementSequence();

        app(SalesReturnService::class)->confirm($salesReturn);

        $invoice->refresh();

        $this->assertSame('60.00', $invoice->remaining_amount);
        $this->assertSame('0.00', $invoice->paid_amount);
    }

    public function test_confirmed_return_creates_customer_credit_when_invoice_is_overpaid(): void
    {
        [$invoice, $product] = $this->createConfirmedCreditInvoice([
            'invoice_cash_amount' => 70,
            'paid_amount' => 70,
            'remaining_amount' => 30,
        ]);

        $salesReturn = $this->createSalesReturn($invoice, $product, quantity: 2);
        $this->prepareStockMovementSequence();

        app(SalesReturnService::class)->confirm($salesReturn);

        $invoice->refresh();

        $this->assertSame('70.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->remaining_amount);
        $this->assertSame(0.0, app(CustomerFinancialService::class)->customerBalance($invoice->customer_id));
        $this->assertSame(10.0, app(CustomerFinancialService::class)->customerCreditBalance($invoice->customer_id));
    }

    public function test_linked_return_uses_original_invoice_effective_price(): void
    {
        [$invoice, $product] = $this->createConfirmedCreditInvoice();

        $salesReturn = $this->createSalesReturn($invoice, $product, quantity: 1);
        $salesReturn->items()->firstOrFail()->forceFill([
            'unit_price' => 999,
            'line_total' => 999,
        ])->saveQuietly();
        $this->prepareStockMovementSequence();

        $confirmed = app(SalesReturnService::class)->confirm($salesReturn);
        $item = $confirmed->items()->firstOrFail();

        $this->assertSame('20.00', $item->unit_price);
        $this->assertSame('20.00', $item->line_total);
        $this->assertSame('20.00', $confirmed->refresh()->total_amount);
    }

    /**
     * @return array{0: SalesInvoice, 1: Product}
     */
    private function createConfirmedCreditInvoice(array $invoiceOverrides = []): array
    {
        $suffix = uniqid();

        $customer = Customer::query()->create([
            'code' => 'C-'.$suffix,
            'name' => 'Test Customer '.$suffix,
            'payment_type' => 'credit',
            'status' => 'active',
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'W-'.$suffix,
            'name' => 'Test Warehouse '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'sku' => 'P-'.$suffix,
            'name_ar' => 'Test Product '.$suffix,
            'sale_price' => 20,
            'status' => 'active',
        ]);

        $invoice = SalesInvoice::query()->create(array_merge([
            'invoice_number' => 'INV-TEST-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'confirmed',
            'payment_type' => 'credit',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'invoice_cash_amount' => 0,
            'remaining_amount' => 100,
            'confirmed_at' => now(),
        ], $invoiceOverrides));

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 20,
            'discount_amount' => 0,
        ]);

        return [$invoice->refresh(), $product];
    }

    private function createSalesReturn(SalesInvoice $invoice, Product $product, int $quantity): SalesReturn
    {
        $salesReturn = SalesReturn::query()->create([
            'return_number' => 'SRT-TEST-'.uniqid(),
            'customer_id' => $invoice->customer_id,
            'sales_invoice_id' => $invoice->id,
            'warehouse_id' => $invoice->warehouse_id,
            'return_date' => now()->toDateString(),
            'status' => 'draft',
            'discount_amount' => 0,
        ]);

        SalesReturnItem::query()->create([
            'sales_return_id' => $salesReturn->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => 20,
        ]);

        return $salesReturn->refresh();
    }

    private function prepareStockMovementSequence(): void
    {
        DB::table('document_sequences')->updateOrInsert(
            [
                'document_type' => 'stock_movement',
                'sequence_date' => now()->toDateString(),
            ],
            [
                'last_number' => 900000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
