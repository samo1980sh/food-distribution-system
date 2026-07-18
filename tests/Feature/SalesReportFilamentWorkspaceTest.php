<?php

namespace Tests\Feature;

use Tests\TestCase;

class SalesReportFilamentWorkspaceTest extends TestCase
{
    public function test_sales_report_uses_a_focused_filament_reporting_workspace(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/SalesReports/Tables/SalesReportsTable.php'));

        $this->assertStringContainsString('FiltersLayout::Modal', $table);
        $this->assertStringContainsString("Section::make('الفترة والحالة')", $table);
        $this->assertStringContainsString("Section::make('نطاق التشغيل')", $table);
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
        $this->assertStringContainsString("emptyStateHeading('لا توجد نتائج في تقرير المبيعات')", $table);
    }

    public function test_sales_report_keeps_existing_filters_printing_and_financial_summaries(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/SalesReports/Tables/SalesReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/SalesReports/Pages/ManageSalesReports.php'));

        foreach ([
            "Filter::make('invoice_date')",
            "SelectFilter::make('status')",
            "SelectFilter::make('payment_type')",
            "SelectFilter::make('customer_id')",
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
            "TextColumn::make('tax_amount')",
            "TextColumn::make('total_amount')",
            "TextColumn::make('invoice_cash_amount')",
            "TextColumn::make('paid_amount')",
            "TextColumn::make('remaining_amount')",
        ] as $amountColumn) {
            $this->assertStringContainsString($amountColumn, $table);
        }

        $this->assertStringContainsString("'reports.sales-invoices.print'", $table);
        $this->assertStringContainsString("'reports.sales-invoices.print-filtered'", $page);
        $this->assertStringContainsString('->summaries(', $table);
        $this->assertStringContainsString('allTableCondition: true', $table);
    }

    public function test_sales_report_hides_secondary_columns_by_default_without_removing_them(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/SalesReports/Tables/SalesReportsTable.php'));

        foreach ([
            'due_date',
            'warehouse.name',
            'vehicle.plate_number',
            'route.name',
            'salesRepresentative.name',
            'subtotal',
            'discount_amount',
            'tax_amount',
            'invoice_cash_amount',
            'credit_limit_overridden',
        ] as $column) {
            $columnStart = strpos($table, "TextColumn::make('{$column}')");

            $this->assertNotFalse($columnStart, "The [{$column}] column is missing.");

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
