<?php

namespace Tests\Feature;

use App\Enums\OperationSource;
use App\Enums\PermissionName;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Filament\Resources\CustomerPayments\Pages\ManageCustomerPayments;
use App\Filament\Resources\DailyClosings\DailyClosingResource;
use App\Filament\Resources\DailyClosings\Pages\ListDailyClosings;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Resources\SalesReturns\Pages\ListSalesReturns;
use App\Filament\Resources\VehicleExpenses\Pages\ManageVehicleExpenses;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\SalesReturn;
use App\Models\VehicleExpense;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use App\Filament\Resources\VehicleExpenses\VehicleExpenseResource;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalResponsibilitySeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_source_audit_columns_and_enum_cast_are_available(): void
    {
        foreach ([
            'sales_invoices',
            'customer_payments',
            'sales_returns',
            'vehicle_expenses',
            'daily_closings',
        ] as $table) {
            $this->assertTrue(Schema::hasColumns($table, [
                'operation_source',
                'administrative_reason',
            ]));
        }

        $invoice = new SalesInvoice([
            'operation_source' => OperationSource::MOBILE_SALES,
        ]);

        $this->assertSame(OperationSource::MOBILE_SALES, $invoice->operation_source);
        $this->assertSame('تطبيق مندوب المبيعات', $invoice->operation_source->label());
    }

    public function test_admin_creation_routes_follow_the_approved_responsibility_matrix(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);
        $salesRepresentative = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);

        $this->actingAs($supervisor);
        $this->assertFalse(SalesInvoiceResource::canCreate());
        $this->assertFalse(SalesReturnResource::canCreate());
        $this->assertFalse(CustomerPaymentResource::canCreate());
        $this->assertFalse(VehicleExpenseResource::canCreate());
        $this->assertFalse(DailyClosingResource::canCreate());

        $this->actingAs($accountant);
        $this->assertTrue(CustomerPaymentResource::canCreate());
        $this->assertTrue(DailyClosingResource::canCreate());
        $this->assertFalse(SalesInvoiceResource::canCreate());
        $this->assertFalse(SalesReturnResource::canCreate());
        $this->assertFalse(VehicleExpenseResource::canCreate());
        $this->assertTrue($accountant->can('create', CustomerPayment::class));
        $this->assertTrue($accountant->can('create', DailyClosing::class));

        $this->actingAs($manager);
        $this->assertTrue(SalesInvoiceResource::canCreate());
        $this->assertTrue(SalesReturnResource::canCreate());
        $this->assertTrue(CustomerPaymentResource::canCreate());
        $this->assertTrue(VehicleExpenseResource::canCreate());
        $this->assertTrue(DailyClosingResource::canCreate());
        $this->assertTrue($manager->can('create', SalesInvoice::class));
        $this->assertTrue($manager->can('create', SalesReturn::class));
        $this->assertTrue($manager->can('create', VehicleExpense::class));

        $this->assertTrue($salesRepresentative->can(PermissionName::SALES_INVOICES_CREATE->value));
        $this->assertFalse($salesRepresentative->can(PermissionName::SALES_INVOICES_CREATE_ADMIN_EXCEPTION->value));
    }

    public function test_mobile_writes_and_api_resources_expose_the_operation_source_contract(): void
    {
        $writeService = file_get_contents(app_path('Services/Api/MobileOperationalWriteService.php'));

        $this->assertStringContainsString('OperationSource::MOBILE_SALES', $writeService);
        $this->assertStringContainsString('OperationSource::MOBILE_DRIVER', $writeService);

        foreach ([
            'SalesInvoiceResource.php',
            'CustomerPaymentResource.php',
            'SalesReturnResource.php',
            'VehicleExpenseResource.php',
            'DailyClosingResource.php',
        ] as $resource) {
            $contents = file_get_contents(app_path('Http/Resources/Api/V1/Operational/'.$resource));

            $this->assertStringContainsString("'operation_source' =>", $contents);
            $this->assertStringContainsString("'operation_source_label' =>", $contents);
            $this->assertStringContainsString("'administrative_reason' =>", $contents);
        }
    }

    public function test_filament_workspaces_are_review_oriented_and_inventory_adjustments_are_explicit(): void
    {
        foreach ([
            'SalesInvoices/SalesInvoiceResource.php',
            'CustomerPayments/CustomerPaymentResource.php',
            'SalesReturns/SalesReturnResource.php',
            'VehicleExpenses/VehicleExpenseResource.php',
            'DailyClosings/DailyClosingResource.php',
        ] as $resource) {
            $contents = file_get_contents(app_path('Filament/Resources/'.$resource));

            $this->assertStringContainsString("return 'المراجعة والاعتماد';", $contents);
            $this->assertStringContainsString('getNavigationBadge', $contents);
        }

        $invoicePage = file_get_contents(app_path('Filament/Resources/SalesInvoices/Pages/ListSalesInvoices.php'));
        $paymentPage = file_get_contents(app_path('Filament/Resources/CustomerPayments/Pages/ManageCustomerPayments.php'));
        $expensePage = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Pages/ManageVehicleExpenses.php'));
        $stockPage = file_get_contents(app_path('Filament/Resources/StockMovements/Pages/ManageStockMovements.php'));
        $stockForm = file_get_contents(app_path('Filament/Resources/StockMovements/Schemas/StockMovementForm.php'));

        $this->assertStringContainsString('فاتورة إدارية استثنائية', $invoicePage);
        $this->assertStringContainsString('تسجيل تحصيل مكتبي', $paymentPage);
        $this->assertStringContainsString('مصروف إداري استثنائي', $expensePage);
        $this->assertStringContainsString('تسوية مخزون إدارية', $stockPage);
        $this->assertStringContainsString('سبب التسوية / التحويل الإداري', $stockForm);
        $this->assertStringContainsString('->required()', $stockForm);
    }

    public function test_accountant_sees_the_real_operational_pages_and_header_actions(): void
    {
        $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);

        $panelProvider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        $this->assertStringContainsString("'التخطيط والتجهيز'", $panelProvider);
        $this->assertStringContainsString("'المراجعة والاعتماد'", $panelProvider);

        $this->actingAs($accountant);

        Livewire::test(ManageCustomerPayments::class)
            ->assertOk()
            ->assertActionVisible('create')
            ->assertActionHasLabel('create', 'تسجيل تحصيل مكتبي');

        Livewire::test(ListDailyClosings::class)
            ->assertOk()
            ->assertActionVisible('create')
            ->assertActionHasLabel('create', 'إغلاق إداري مؤقت');

        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $this->actingAs($manager);

        Livewire::test(ListSalesInvoices::class)
            ->assertOk()
            ->assertActionVisible('create')
            ->assertActionHasLabel('create', 'فاتورة إدارية استثنائية');

        Livewire::test(ListSalesReturns::class)
            ->assertOk()
            ->assertActionVisible('create')
            ->assertActionHasLabel('create', 'مرتجع إداري استثنائي');

        Livewire::test(ManageVehicleExpenses::class)
            ->assertOk()
            ->assertActionVisible('create')
            ->assertActionHasLabel('create', 'مصروف إداري استثنائي');
    }

}
