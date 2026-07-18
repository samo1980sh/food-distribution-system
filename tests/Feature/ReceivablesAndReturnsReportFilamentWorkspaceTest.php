<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReceivablesAndReturnsReportFilamentWorkspaceTest extends TestCase
{
    public function test_receivables_and_returns_reports_use_focused_filament_workspaces(): void
    {
        $reports = [
            [
                'path' => 'Filament/Resources/CustomerPaymentReports/Tables/CustomerPaymentReportsTable.php',
                'section' => "Section::make('الفترة والحالة')",
                'scope_section' => "Section::make('العميل ونطاق التشغيل')",
                'empty_heading' => "emptyStateHeading('لا توجد نتائج في تقرير التحصيلات')",
            ],
            [
                'path' => 'Filament/Resources/SalesReturnReports/Tables/SalesReturnReportsTable.php',
                'section' => "Section::make('الفترة والتصنيف')",
                'scope_section' => "Section::make('العميل ونطاق التشغيل')",
                'empty_heading' => "emptyStateHeading('لا توجد نتائج في تقرير مرتجعات البيع')",
            ],
        ];

        foreach ($reports as $report) {
            $table = file_get_contents(app_path($report['path']));

            $this->assertStringContainsString('FiltersLayout::Modal', $table);
            $this->assertStringContainsString($report['section'], $table);
            $this->assertStringContainsString($report['scope_section'], $table);
            $this->assertStringContainsString('filtersTriggerAction', $table);
            $this->assertStringContainsString('filtersApplyAction', $table);
            $this->assertStringContainsString('FiltersResetActionPosition::Footer', $table);
            $this->assertStringContainsString('ColumnManagerLayout::Modal', $table);
            $this->assertStringContainsString('columnManagerColumns(2)', $table);
            $this->assertStringContainsString('columnManagerTriggerAction', $table);
            $this->assertStringContainsString('ColumnManagerResetActionPosition::Footer', $table);
            $this->assertStringContainsString('stackedOnMobile', $table);
            $this->assertStringContainsString('persistSearchInSession', $table);
            $this->assertStringContainsString('persistFiltersInSession', $table);
            $this->assertStringContainsString('persistSortInSession', $table);
            $this->assertStringContainsString('paginationPageOptions([10, 25, 50, 100])', $table);
            $this->assertStringContainsString($report['empty_heading'], $table);
        }
    }

    public function test_customer_payment_report_keeps_existing_filters_printing_and_financial_summaries(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/CustomerPaymentReports/Tables/CustomerPaymentReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/CustomerPaymentReports/Pages/ManageCustomerPaymentReports.php'));

        foreach ([
            "Filter::make('payment_date')",
            "SelectFilter::make('status')",
            "SelectFilter::make('payment_method')",
            "SelectFilter::make('customer_id')",
            "SelectFilter::make('sales_invoice_id')",
            "SelectFilter::make('warehouse_id')",
            "SelectFilter::make('vehicle_id')",
            "SelectFilter::make('route_id')",
            "SelectFilter::make('sales_representative_id')",
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        foreach ([
            "TextColumn::make('amount')",
            "TextColumn::make('cash_amount')",
            "TextColumn::make('non_cash_amount')",
        ] as $amountColumn) {
            $this->assertStringContainsString($amountColumn, $table);
        }

        $this->assertStringContainsString("'reports.customer-payments.print'", $table);
        $this->assertStringContainsString("'reports.customer-payments.print-filtered'", $page);
        $this->assertStringContainsString("->where('payment_method', 'cash')", $table);
        $this->assertStringContainsString("->whereIn('payment_method'", $table);
        $this->assertStringContainsString('->summaries(', $table);
        $this->assertStringContainsString('allTableCondition: true', $table);
    }

    public function test_sales_return_report_keeps_existing_filters_printing_and_financial_summaries(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/SalesReturnReports/Tables/SalesReturnReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/SalesReturnReports/Pages/ManageSalesReturnReports.php'));

        foreach ([
            "Filter::make('return_date')",
            "SelectFilter::make('status')",
            "SelectFilter::make('return_reason')",
            "SelectFilter::make('customer_id')",
            "SelectFilter::make('sales_invoice_id')",
            "SelectFilter::make('warehouse_id')",
            "SelectFilter::make('vehicle_id')",
            "SelectFilter::make('route_id')",
            "SelectFilter::make('sales_representative_id')",
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        foreach ([
            "TextColumn::make('subtotal')",
            "TextColumn::make('discount_amount')",
            "TextColumn::make('total_amount')",
        ] as $amountColumn) {
            $this->assertStringContainsString($amountColumn, $table);
        }

        $this->assertStringContainsString("'reports.sales-returns.print'", $table);
        $this->assertStringContainsString("'reports.sales-returns.print-filtered'", $page);
        $this->assertStringContainsString('->summaries(', $table);
        $this->assertStringContainsString('allTableCondition: true', $table);
    }

    public function test_secondary_receivables_and_returns_columns_remain_available_but_hidden_by_default(): void
    {
        $reports = [
            'Filament/Resources/CustomerPaymentReports/Tables/CustomerPaymentReportsTable.php' => [
                'warehouse.name',
                'vehicle.plate_number',
                'route.name',
                'salesRepresentative.name',
                'cash_amount',
                'non_cash_amount',
                'reference_number',
                'confirmed_at',
            ],
            'Filament/Resources/SalesReturnReports/Tables/SalesReturnReportsTable.php' => [
                'warehouse.name',
                'vehicle.plate_number',
                'route.name',
                'salesRepresentative.name',
                'subtotal',
                'discount_amount',
                'confirmed_at',
            ],
        ];

        foreach ($reports as $path => $columns) {
            $table = file_get_contents(app_path($path));

            foreach ($columns as $column) {
                $columnStart = strpos($table, "TextColumn::make('{$column}')");

                $this->assertNotFalse($columnStart, "The [{$column}] column is missing from [{$path}].");

                $nextColumnStart = strpos($table, 'TextColumn::make(', $columnStart + 1);
                $columnDefinition = substr(
                    $table,
                    $columnStart,
                    $nextColumnStart === false ? null : $nextColumnStart - $columnStart,
                );

                $this->assertStringContainsString(
                    'toggleable(isToggledHiddenByDefault: true)',
                    $columnDefinition,
                    "The [{$column}] column should remain available but hidden by default.",
                );
            }
        }
    }
}
