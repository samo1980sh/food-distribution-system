<?php

namespace Tests\Feature;

use App\Services\Reports\ProfitReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfitReportQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_net_profit_from_confirmed_invoices_and_returns_only(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            $this->insertInvoices();
            $this->insertReturns();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $entries = app(ProfitReportQuery::class)
            ->build()
            ->orderBy('entry_type')
            ->get();

        $this->assertCount(2, $entries);

        $invoice = $entries->firstWhere('entry_type', 'invoice');
        $return = $entries->firstWhere('entry_type', 'return');

        $this->assertNotNull($invoice);
        $this->assertNotNull($return);

        $this->assertSame('INV-TEST-001', $invoice->document_number);
        $this->assertEquals(2.000, (float) $invoice->quantity);
        $this->assertEquals(1000.00, (float) $invoice->sales_amount);
        $this->assertEquals(600.00, (float) $invoice->cost_amount);
        $this->assertEquals(400.00, (float) $invoice->profit_amount);
        $this->assertEquals(40.00, (float) $invoice->margin_percent);

        $this->assertSame('SRT-TEST-001', $return->document_number);
        $this->assertEquals(-1.000, (float) $return->quantity);
        $this->assertEquals(-250.00, (float) $return->sales_amount);
        $this->assertEquals(-150.00, (float) $return->cost_amount);
        $this->assertEquals(-100.00, (float) $return->profit_amount);
        $this->assertEquals(40.00, (float) $return->margin_percent);

        $this->assertEquals(1.000, (float) $entries->sum('quantity'));
        $this->assertEquals(750.00, (float) $entries->sum('sales_amount'));
        $this->assertEquals(450.00, (float) $entries->sum('cost_amount'));
        $this->assertEquals(300.00, (float) $entries->sum('profit_amount'));
    }

    private function insertInvoices(): void
    {
        DB::table('sales_invoices')->insert([
            [
                'id' => 1,
                'invoice_number' => 'INV-TEST-001',
                'customer_id' => 1,
                'vehicle_id' => null,
                'route_id' => null,
                'warehouse_id' => 1,
                'sales_representative_id' => null,
                'invoice_date' => '2026-07-10',
                'status' => 'confirmed',
                'payment_type' => 'cash',
                'subtotal' => 1000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 1000,
                'paid_amount' => 1000,
                'invoice_cash_amount' => 1000,
                'remaining_amount' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'invoice_number' => 'INV-DRAFT-001',
                'customer_id' => 1,
                'vehicle_id' => null,
                'route_id' => null,
                'warehouse_id' => 1,
                'sales_representative_id' => null,
                'invoice_date' => '2026-07-10',
                'status' => 'draft',
                'payment_type' => 'cash',
                'subtotal' => 500,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 500,
                'paid_amount' => 0,
                'invoice_cash_amount' => 0,
                'remaining_amount' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('sales_invoice_items')->insert([
            [
                'sales_invoice_id' => 1,
                'product_id' => 1,
                'batch_number' => null,
                'expiry_date' => null,
                'quantity' => 2,
                'unit_price' => 500,
                'unit_cost' => 300,
                'discount_amount' => 0,
                'line_total' => 1000,
                'total_cost' => 600,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sales_invoice_id' => 2,
                'product_id' => 1,
                'batch_number' => null,
                'expiry_date' => null,
                'quantity' => 1,
                'unit_price' => 500,
                'unit_cost' => 300,
                'discount_amount' => 0,
                'line_total' => 500,
                'total_cost' => 300,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function insertReturns(): void
    {
        DB::table('sales_returns')->insert([
            [
                'id' => 1,
                'return_number' => 'SRT-TEST-001',
                'customer_id' => 1,
                'sales_invoice_id' => 1,
                'vehicle_id' => null,
                'route_id' => null,
                'warehouse_id' => 1,
                'sales_representative_id' => null,
                'return_date' => '2026-07-11',
                'status' => 'confirmed',
                'return_reason' => null,
                'subtotal' => 250,
                'discount_amount' => 0,
                'total_amount' => 250,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'return_number' => 'SRT-CANCELLED-001',
                'customer_id' => 1,
                'sales_invoice_id' => 1,
                'vehicle_id' => null,
                'route_id' => null,
                'warehouse_id' => 1,
                'sales_representative_id' => null,
                'return_date' => '2026-07-11',
                'status' => 'cancelled',
                'return_reason' => null,
                'subtotal' => 100,
                'discount_amount' => 0,
                'total_amount' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('sales_return_items')->insert([
            [
                'sales_return_id' => 1,
                'product_id' => 1,
                'batch_number' => null,
                'expiry_date' => null,
                'quantity' => 1,
                'unit_price' => 250,
                'unit_cost' => 150,
                'line_total' => 250,
                'total_cost' => 150,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sales_return_id' => 2,
                'product_id' => 1,
                'batch_number' => null,
                'expiry_date' => null,
                'quantity' => 1,
                'unit_price' => 100,
                'unit_cost' => 60,
                'line_total' => 100,
                'total_cost' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
