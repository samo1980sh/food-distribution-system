<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalDataProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_is_deactivated_instead_of_deleted_from_application_policies(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $records = [
            new Area(),
            new Customer(),
            new DistributionRoute(),
            new Employee(),
            new Product(),
            new ProductCategory(),
            new Unit(),
            new Vehicle(),
            new Warehouse(),
        ];

        foreach ($records as $record) {
            $this->assertFalse(
                $superAdmin->can('delete', $record),
                $record::class.' must be deactivated instead of deleted.',
            );
        }
    }

    public function test_customer_with_invoice_history_cannot_be_deleted_at_database_level(): void
    {
        $context = $this->invoiceContext('CUSTOMER');

        $this->assertDeleteBlocked($context['customer']);
        $this->assertDatabaseHas('sales_invoices', [
            'id' => $context['invoice']->id,
            'customer_id' => $context['customer']->id,
        ]);
    }

    public function test_warehouse_with_invoice_history_cannot_be_deleted_at_database_level(): void
    {
        $context = $this->invoiceContext('WAREHOUSE');

        $this->assertDeleteBlocked($context['warehouse']);
        $this->assertDatabaseHas('sales_invoices', [
            'id' => $context['invoice']->id,
            'warehouse_id' => $context['warehouse']->id,
        ]);
    }

    public function test_product_with_invoice_history_cannot_be_deleted_at_database_level(): void
    {
        $context = $this->invoiceContext('PRODUCT');

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $context['invoice']->id,
            'product_id' => $context['product']->id,
            'quantity' => 1,
            'unit_price' => 10,
            'discount_amount' => 0,
            'line_total' => 10,
        ]);

        $this->assertDeleteBlocked($context['product']);
        $this->assertDatabaseHas('sales_invoice_items', [
            'sales_invoice_id' => $context['invoice']->id,
            'product_id' => $context['product']->id,
        ]);
    }

    /** @return array{customer: Customer, warehouse: Warehouse, product: Product, invoice: SalesInvoice} */
    private function invoiceContext(string $suffix): array
    {
        $customer = Customer::query()->create([
            'code' => 'HIST-CUS-'.$suffix,
            'name' => 'Historical Customer '.$suffix,
            'payment_type' => 'credit',
            'status' => 'active',
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'HIST-WH-'.$suffix,
            'name' => 'Historical Warehouse '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'sku' => 'HIST-SKU-'.$suffix,
            'name_ar' => 'Historical Product '.$suffix,
            'purchase_price' => 5,
            'sale_price' => 10,
            'status' => 'active',
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'HIST-INV-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => today(),
            'status' => 'draft',
            'payment_type' => 'credit',
            'subtotal' => 10,
            'total_amount' => 10,
            'remaining_amount' => 10,
        ]);

        return compact('customer', 'warehouse', 'product', 'invoice');
    }

    private function assertDeleteBlocked(object $record): void
    {
        try {
            $record->delete();
            $this->fail('Expected the database to reject deletion of referenced master data.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'foreign key constraint',
                strtolower($exception->getMessage()),
            );
        }
    }
}
