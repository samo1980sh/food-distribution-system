<?php

namespace Tests\Feature;

use Tests\TestCase;

class SalesInvoiceFilamentWorkflowTest extends TestCase
{
    public function test_sales_invoice_resource_exposes_full_page_workflow(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/SalesInvoices/SalesInvoiceResource.php'));

        $this->assertStringContainsString("'index' => ListSalesInvoices::route('/')", $resource);
        $this->assertStringContainsString("'create' => CreateSalesInvoice::route('/create')", $resource);
        $this->assertStringContainsString("'view' => ViewSalesInvoice::route('/{record}')", $resource);
        $this->assertStringContainsString("'edit' => EditSalesInvoice::route('/{record}/edit')", $resource);
        $this->assertStringContainsString('SalesInvoiceInfolist::configure', $resource);
    }

    public function test_invoice_workspace_contains_persistent_table_and_structured_detail_components(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/SalesInvoices/Tables/SalesInvoicesTable.php'));
        $form = file_get_contents(app_path('Filament/Resources/SalesInvoices/Schemas/SalesInvoiceForm.php'));
        $infolist = file_get_contents(app_path('Filament/Resources/SalesInvoices/Schemas/SalesInvoiceInfolist.php'));

        $this->assertStringContainsString('persistSearchInSession', $table);
        $this->assertStringContainsString('persistFiltersInSession', $table);
        $this->assertStringContainsString('emptyStateHeading', $table);
        $this->assertStringContainsString('ActionGroup::make', $table);
        $this->assertStringContainsString("Section::make('بيانات العميل والاستحقاق')", $form);
        $this->assertStringContainsString("Section::make('مواد الفاتورة')", $form);
        $this->assertStringContainsString('RepeatableEntry::make', $infolist);
    }
}
