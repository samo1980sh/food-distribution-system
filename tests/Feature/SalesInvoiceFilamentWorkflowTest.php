<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Resources\SalesInvoices\Pages\ManageSalesInvoices;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SalesInvoiceFilamentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_invoice_workspace_hides_administrative_invoice_creation_and_uses_native_slide_over_details(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $record = $this->seedSalesInvoiceRecord($manager->id);

        $this->actingAs($manager);

        Livewire::test(ListSalesInvoices::class)
            ->assertOk()
            ->assertActionDoesNotExist('create');

        Livewire::test(ManageSalesInvoices::class)
            ->assertOk();

        Livewire::test(ListSalesInvoices::class)
            ->assertOk()
            ->assertTableActionExists('details')
            ->assertTableActionDoesNotHaveUrl('details', '#', $record)
            ->mountTableAction('details', $record)
            ->assertSet('mountedActions.0.name', 'details')
            ->assertSet('mountedActions.0.context.table', true)
            ->assertSet('mountedActions.0.context.recordKey', (string) $record);
    }

    public function test_sales_invoice_view_action_is_configured_as_slide_over_with_footer_actions(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/SalesInvoices/Tables/SalesInvoicesTable.php'));
        $actions = file_get_contents(app_path('Filament/Resources/SalesInvoices/Actions/SalesInvoiceActions.php'));

        $this->assertStringContainsString("Action::make('details')", $table);
        $this->assertStringContainsString("->label('عرض التفاصيل')", $table);
        $this->assertStringContainsString('->slideOver()', $table);
        $this->assertStringContainsString('->schema(fn (Schema $schema): Schema => SalesInvoiceInfolist::configure($schema))', $table);
        $this->assertStringContainsString("->label('اعتماد الفاتورة')", $actions);
        $this->assertStringContainsString("->label('طباعة الفاتورة')", $actions);
    }

    private function seedSalesInvoiceRecord(int $createdBy): int
    {
        $unitId = DB::table('units')->insertGetId([
            'code' => 'PCS',
            'name_ar' => 'قطعة',
            'symbol' => 'PCS',
            'status' => 'active',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'code' => 'CUST-001',
            'name' => 'عميل اختبار',
            'owner_name' => 'مالك اختبار',
            'phone' => '0000000000',
            'mobile' => '0000000000',
            'customer_type' => 'grocery',
            'area_id' => null,
            'route_id' => null,
            'address' => 'اختبار',
            'latitude' => null,
            'longitude' => null,
            'credit_limit' => 0,
            'payment_type' => 'cash',
            'status' => 'active',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'vehicle_id' => null,
            'code' => 'WH-001',
            'name' => 'مستودع اختبار',
            'type' => 'main',
            'address' => 'اختبار',
            'status' => 'active',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'sku' => 'SKU-001',
            'barcode' => null,
            'name_ar' => 'منتج اختبار',
            'category_id' => null,
            'unit_id' => $unitId,
            'purchase_price' => 10,
            'sale_price' => 12,
            'wholesale_price' => 11,
            'min_stock' => 0,
            'has_expiry' => true,
            'status' => 'active',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = DB::table('sales_invoices')->insertGetId([
            'invoice_number' => 'INV-001',
            'customer_id' => $customerId,
            'vehicle_id' => null,
            'route_id' => null,
            'warehouse_id' => $warehouseId,
            'sales_representative_id' => null,
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'payment_type' => 'cash',
            'subtotal' => 40,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 40,
            'paid_amount' => 0,
            'remaining_amount' => 40,
            'notes' => null,
            'created_by' => $createdBy,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_invoice_items')->insert([
            'sales_invoice_id' => $invoiceId,
            'product_id' => $productId,
            'batch_number' => null,
            'expiry_date' => null,
            'quantity' => 1,
            'unit_price' => 40,
            'discount_amount' => 0,
            'line_total' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $invoiceId;
    }
}
