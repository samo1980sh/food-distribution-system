<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\VehicleLoadItem;
use App\Models\Warehouse;
use App\Services\Distribution\DailyClosingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DailyClosingRefreshTotalsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_refresh_totals_aggregates_quantities_sales_returns_and_cash_breakdown(): void
    {
        $suffix = uniqid();
        $date = '2026-07-04';

        $customer = Customer::query()->create([
            'code' => 'C-DCL-TOTAL-'.$suffix,
            'name' => 'Daily Closing Customer '.$suffix,
            'payment_type' => 'credit',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::query()->create([
            'code' => 'V-DCL-TOTAL-'.$suffix,
            'plate_number' => 'DCL-TOTAL-'.$suffix,
            'status' => 'active',
        ]);

        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'W-DCL-SRC-'.$suffix,
            'name' => 'Daily Closing Source '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'W-DCL-CLOSE-'.$suffix,
            'name' => 'Daily Closing Warehouse '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'sku' => 'P-DCL-TOTAL-'.$suffix,
            'name_ar' => 'Daily Closing Product '.$suffix,
            'sale_price' => 20,
            'status' => 'active',
        ]);

        $vehicleLoad = VehicleLoad::query()->create([
            'load_number' => 'VLD-DCL-TOTAL-'.$suffix,
            'vehicle_id' => $vehicle->id,
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $warehouse->id,
            'load_date' => $date,
            'status' => 'approved',
            'total_quantity' => 10,
            'total_cost' => 0,
        ]);

        VehicleLoadItem::query()->create([
            'vehicle_load_id' => $vehicleLoad->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 0,
            'total_cost' => 0,
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-DCL-TOTAL-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => $date,
            'status' => 'confirmed',
            'payment_type' => 'partial',
            'subtotal' => 80,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 80,
            'paid_amount' => 20,
            'invoice_cash_amount' => 20,
            'remaining_amount' => 60,
            'confirmed_at' => now(),
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 20,
            'discount_amount' => 0,
            'line_total' => 80,
        ]);

        $salesReturn = SalesReturn::query()->create([
            'return_number' => 'SRT-DCL-TOTAL-'.$suffix,
            'customer_id' => $customer->id,
            'sales_invoice_id' => $invoice->id,
            'warehouse_id' => $warehouse->id,
            'return_date' => $date,
            'status' => 'confirmed',
            'subtotal' => 20,
            'discount_amount' => 0,
            'total_amount' => 20,
            'confirmed_at' => now(),
        ]);

        SalesReturnItem::query()->create([
            'sales_return_id' => $salesReturn->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 20,
            'line_total' => 20,
        ]);

        CustomerPayment::query()->create([
            'payment_number' => 'PAY-DCL-CASH-'.$suffix,
            'customer_id' => $customer->id,
            'sales_invoice_id' => $invoice->id,
            'warehouse_id' => $warehouse->id,
            'payment_date' => $date,
            'payment_method' => 'cash',
            'status' => 'confirmed',
            'amount' => 30,
            'confirmed_at' => now(),
        ]);

        CustomerPayment::query()->create([
            'payment_number' => 'PAY-DCL-BANK-'.$suffix,
            'customer_id' => $customer->id,
            'sales_invoice_id' => $invoice->id,
            'warehouse_id' => $warehouse->id,
            'payment_date' => $date,
            'payment_method' => 'bank_transfer',
            'status' => 'confirmed',
            'amount' => 15,
            'confirmed_at' => now(),
        ]);

        $closing = DailyClosing::query()->create([
            'closing_number' => 'DCL-TOTAL-'.$suffix,
            'closing_date' => $date,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'actual_cash_amount' => 55,
        ]);

        $closing = app(DailyClosingService::class)->refreshTotals($closing);

        $item = $closing->items()
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertSame('10.000', $closing->total_loaded_quantity);
        $this->assertSame('4.000', $closing->total_sold_quantity);
        $this->assertSame('1.000', $closing->total_returned_quantity);

        $this->assertSame('10.000', $item->loaded_quantity);
        $this->assertSame('4.000', $item->sold_quantity);
        $this->assertSame('1.000', $item->returned_quantity);
        $this->assertSame('7.000', $item->expected_quantity);

        $this->assertSame('80.00', $closing->total_sales_amount);
        $this->assertSame('20.00', $closing->total_returns_amount);
        $this->assertSame('45.00', $closing->total_collections_amount);

        $this->assertSame('20.00', $closing->invoice_cash_amount);
        $this->assertSame('30.00', $closing->cash_collections_amount);
        $this->assertSame('15.00', $closing->bank_transfer_collections_amount);
        $this->assertSame('15.00', $closing->non_cash_collections_amount);

        $this->assertSame('50.00', $closing->expected_cash_amount);
        $this->assertSame('5.00', $closing->cash_difference);
    }
}