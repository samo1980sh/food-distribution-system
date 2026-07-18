<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProfitReportFilamentWorkspaceTest extends TestCase
{
    public function test_profit_report_uses_a_focused_filament_financial_workspace(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/ProfitReports/Tables/ProfitReportsTable.php'));

        foreach ([
            'FiltersLayout::Modal',
            "Section::make('الفترة ونوع الحركة')",
            "Section::make('العميل والمستودع')",
            "Section::make('نطاق التوزيع')",
            "->label('خيارات التقرير')",
            "->modalHeading('خيارات تصفية تقرير الأرباح التقريبية')",
            'ColumnManagerLayout::Modal',
            '->columnManagerColumns(2)',
            "->label('الأعمدة')",
            "->modalHeading('إدارة أعمدة تقرير الأرباح التقريبية')",
            '->persistSearchInSession()',
            '->persistColumnSearchesInSession()',
            '->persistFiltersInSession()',
            '->persistSortInSession()',
            '->paginationPageOptions([10, 25, 50, 100])',
            '->defaultPaginationPageOption(25)',
            '->stackedOnMobile()',
            "->emptyStateHeading('لا توجد نتائج في تقرير الأرباح التقريبية')",
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }
    }

    public function test_profit_report_keeps_existing_filters_printing_and_financial_summaries(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/ProfitReports/Tables/ProfitReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/ProfitReports/Pages/ManageProfitReports.php'));
        $query = file_get_contents(app_path('Services/Reports/ProfitReportQuery.php'));

        foreach ([
            "Filter::make('entry_date')",
            "SelectFilter::make('entry_type')",
            "SelectFilter::make('customer_id')",
            "SelectFilter::make('warehouse_id')",
            "SelectFilter::make('vehicle_id')",
            "SelectFilter::make('route_id')",
            "SelectFilter::make('sales_representative_id')",
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        foreach ([
            "TextColumn::make('quantity')",
            "TextColumn::make('sales_amount')",
            "TextColumn::make('cost_amount')",
            "TextColumn::make('profit_amount')",
            "TextColumn::make('margin_percent')",
            "->sum('sales_amount')",
            "->sum('profit_amount')",
            'return ($profit / $sales) * 100;',
            'pageCondition: false',
            'allTableCondition: true',
            "Action::make('print')",
            "'invoice' => route('reports.sales-invoices.print'",
            "'return' => route('reports.sales-returns.print'",
            'PermissionName::REPORT_PROFIT->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }

        foreach ([
            "Action::make('printFiltered')",
            "'reports.profit.print-filtered'",
            "'filters' => \$this->tableFilters ?? []",
            "'search' => \$this->getTableSearch()",
            'PermissionName::REPORT_PROFIT->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $page);
        }

        foreach ([
            "->where('sales_invoices.status', 'confirmed')",
            "->where('sales_returns.status', 'confirmed')",
            '(sales_invoices.total_amount - COALESCE(invoice_item_totals.total_cost, 0)) as profit_amount',
            '(COALESCE(return_item_totals.total_cost, 0) - sales_returns.total_amount) as profit_amount',
            "->selectRaw('-sales_returns.total_amount as sales_amount')",
            "->selectRaw('-COALESCE(return_item_totals.total_cost, 0) as cost_amount')",
        ] as $expected) {
            $this->assertStringContainsString($expected, $query);
        }
    }

    public function test_secondary_profit_report_columns_remain_available_but_hidden_by_default(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/ProfitReports/Tables/ProfitReportsTable.php'));

        foreach ([
            'quantity',
            'warehouse.name',
            'vehicle.plate_number',
            'route.name',
            'salesRepresentative.name',
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
