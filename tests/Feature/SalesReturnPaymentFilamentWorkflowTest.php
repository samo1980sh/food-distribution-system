<?php

namespace Tests\Feature;

use Tests\TestCase;

class SalesReturnPaymentFilamentWorkflowTest extends TestCase
{
    public function test_sales_return_resource_exposes_full_page_workflow(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/SalesReturns/SalesReturnResource.php'));

        $this->assertStringContainsString("'index' => ListSalesReturns::route('/')", $resource);
        $this->assertStringContainsString("'create' => CreateSalesReturn::route('/create')", $resource);
        $this->assertStringContainsString("'view' => ViewSalesReturn::route('/{record}')", $resource);
        $this->assertStringContainsString("'edit' => EditSalesReturn::route('/{record}/edit')", $resource);
        $this->assertStringContainsString('SalesReturnInfolist::configure', $resource);
    }

    public function test_customer_payment_resource_uses_hybrid_slide_over_workflow(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/CustomerPayments/CustomerPaymentResource.php'));
        $managePage = file_get_contents(app_path('Filament/Resources/CustomerPayments/Pages/ManageCustomerPayments.php'));
        $viewPage = file_get_contents(app_path('Filament/Resources/CustomerPayments/Pages/ViewCustomerPayment.php'));
        $table = file_get_contents(app_path('Filament/Resources/CustomerPayments/Tables/CustomerPaymentsTable.php'));

        $this->assertStringContainsString("'index' => ManageCustomerPayments::route('/')", $resource);
        $this->assertStringContainsString("'view' => ViewCustomerPayment::route('/{record}')", $resource);
        $this->assertStringNotContainsString("'create' =>", $resource);
        $this->assertStringNotContainsString("'edit' =>", $resource);
        $this->assertStringContainsString('CustomerPaymentInfolist::configure', $resource);
        $this->assertStringContainsString('CreateAction::make()', $managePage);
        $this->assertStringContainsString('->slideOver()', $managePage);
        $this->assertStringContainsString('EditAction::make()', $table);
        $this->assertStringContainsString('->slideOver()', $table);
        $this->assertStringContainsString('EditAction::make()', $viewPage);
        $this->assertStringContainsString('->slideOver()', $viewPage);
    }

    public function test_return_and_payment_workspaces_use_persistent_tables_sections_and_action_groups(): void
    {
        $returnTable = file_get_contents(app_path('Filament/Resources/SalesReturns/Tables/SalesReturnsTable.php'));
        $returnForm = file_get_contents(app_path('Filament/Resources/SalesReturns/Schemas/SalesReturnForm.php'));
        $returnInfolist = file_get_contents(app_path('Filament/Resources/SalesReturns/Schemas/SalesReturnInfolist.php'));
        $paymentTable = file_get_contents(app_path('Filament/Resources/CustomerPayments/Tables/CustomerPaymentsTable.php'));
        $paymentForm = file_get_contents(app_path('Filament/Resources/CustomerPayments/Schemas/CustomerPaymentForm.php'));
        $paymentInfolist = file_get_contents(app_path('Filament/Resources/CustomerPayments/Schemas/CustomerPaymentInfolist.php'));

        foreach ([$returnTable, $paymentTable] as $table) {
            $this->assertStringContainsString('persistSearchInSession', $table);
            $this->assertStringContainsString('persistFiltersInSession', $table);
            $this->assertStringContainsString('emptyStateHeading', $table);
            $this->assertStringContainsString('ActionGroup::make', $table);
        }

        $this->assertStringContainsString("Section::make('المرجع وسبب المرتجع')", $returnForm);
        $this->assertStringContainsString("Section::make('مواد المرتجع')", $returnForm);
        $this->assertStringContainsString('RepeatableEntry::make', $returnInfolist);
        $this->assertStringContainsString("Section::make('العميل والفاتورة')", $paymentForm);
        $this->assertStringContainsString("Section::make('بيانات التحصيل')", $paymentForm);
        $this->assertStringContainsString("Section::make('أثر الفاتورة')", $paymentInfolist);
    }
}
