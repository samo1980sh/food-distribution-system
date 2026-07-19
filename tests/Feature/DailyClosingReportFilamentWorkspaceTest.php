<?php

namespace Tests\Feature;

use Tests\TestCase;

class DailyClosingReportFilamentWorkspaceTest extends TestCase
{
    public function test_daily_closing_report_uses_a_focused_filament_reconciliation_workspace(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/DailyClosingReports/Tables/DailyClosingReportsTable.php'));

        foreach ([
            'FiltersLayout::AboveContentCollapsible',
            "Section::make('خيارات تصفية الإغلاقات')",
            '->filtersFormColumns(3)',
            "->label('عرض النتائج')",
            'ColumnManagerLayout::Modal',
            '->columnManagerColumns(2)',
            "->label('الأعمدة')",
            "->modalHeading('إدارة أعمدة تقرير الإغلاق اليومي')",
            '->persistSearchInSession()',
            '->persistColumnSearchesInSession()',
            '->persistFiltersInSession()',
            '->persistSortInSession()',
            '->paginationPageOptions([10, 25, 50, 100])',
            '->defaultPaginationPageOption(25)',
            '->stackedOnMobile()',
            "->emptyStateHeading('لا توجد إغلاقات يومية ضمن النطاق المحدد')",
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }

        foreach ([
            "TextColumn::make('closing_number')",
            "->label('الإغلاق')",
            "TextColumn::make('warehouse.name')",
            "->label('نطاق الإغلاق')",
            "TextColumn::make('net_sales_amount')",
            "TextColumn::make('total_collections_amount')",
            "TextColumn::make('total_vehicle_expenses_amount')",
            "TextColumn::make('expected_cash_amount')",
            "TextColumn::make('actual_cash_amount')",
            "TextColumn::make('cash_difference')",
            "TextColumn::make('status')",
        ] as $primaryColumn) {
            $this->assertStringContainsString($primaryColumn, $table);
        }
    }

    public function test_daily_closing_report_keeps_existing_filters_calculations_summaries_printing_and_permissions(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/DailyClosingReports/Tables/DailyClosingReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/DailyClosingReports/Pages/ManageDailyClosingReports.php'));
        $resource = file_get_contents(app_path('Filament/Resources/DailyClosingReports/DailyClosingReportResource.php'));

        foreach ([
            "Filter::make('closing_date')",
            "SelectFilter::make('status')",
            "SelectFilter::make('warehouse_id')",
            "SelectFilter::make('vehicle_id')",
            "SelectFilter::make('route_id')",
            "SelectFilter::make('sales_representative_id')",
            "->whereDate('closing_date', '>=', \$date)",
            "->whereDate('closing_date', '<=', \$date)",
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        foreach ([
            '(float) $record->total_sales_amount',
            '- (float) $record->total_returns_amount',
            "(float) \$query->sum('total_sales_amount')",
            "- (float) \$query->sum('total_returns_amount')",
            "TextColumn::make('total_opening_quantity')",
            "TextColumn::make('total_movement_in_quantity')",
            "TextColumn::make('total_movement_out_quantity')",
            "TextColumn::make('total_expected_quantity')",
            "TextColumn::make('total_loaded_quantity')",
            "TextColumn::make('total_sold_quantity')",
            "TextColumn::make('total_returned_quantity')",
            "TextColumn::make('total_sales_amount')",
            "TextColumn::make('total_returns_amount')",
            "TextColumn::make('invoice_cash_amount')",
            "TextColumn::make('cash_collections_amount')",
            "TextColumn::make('non_cash_collections_amount')",
            'Count::make()',
            'Sum::make()',
            'Summarizer::make()',
            'pageCondition: false',
            'allTableCondition: true',
            "Action::make('print')",
            "'reports.daily-closings.print'",
            'PermissionName::REPORT_DAILY_CLOSINGS->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }

        foreach ([
            "Action::make('printFiltered')",
            "'reports.daily-closings.print-filtered'",
            "'filters' => \$this->tableFilters ?? []",
            "'search' => \$this->getTableSearch()",
            'PermissionName::REPORT_DAILY_CLOSINGS->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $page);
        }

        $this->assertStringContainsString('PermissionName::REPORT_DAILY_CLOSINGS->value', $resource);
    }

    public function test_secondary_daily_closing_columns_remain_available_but_hidden_by_default(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/DailyClosingReports/Tables/DailyClosingReportsTable.php'));

        foreach ([
            'closing_date',
            'vehicle.plate_number',
            'route.name',
            'salesRepresentative.name',
            'total_opening_quantity',
            'total_movement_in_quantity',
            'total_movement_out_quantity',
            'total_expected_quantity',
            'total_loaded_quantity',
            'total_sold_quantity',
            'total_returned_quantity',
            'total_sales_amount',
            'total_returns_amount',
            'invoice_cash_amount',
            'cash_collections_amount',
            'non_cash_collections_amount',
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
