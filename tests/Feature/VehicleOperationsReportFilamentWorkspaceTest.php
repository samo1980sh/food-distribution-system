<?php

namespace Tests\Feature;

use Tests\TestCase;

class VehicleOperationsReportFilamentWorkspaceTest extends TestCase
{
    public function test_vehicle_load_and_expense_reports_use_focused_filament_workspaces(): void
    {
        $reports = [
            [
                'path' => 'Filament/Resources/VehicleLoadReports/Tables/VehicleLoadReportsTable.php',
                'sections' => [
                    "Section::make('الفترة والحالة')",
                    "Section::make('السيارة وفريق التوزيع')",
                    "Section::make('المستودعات')",
                ],
                'empty_heading' => "emptyStateHeading('لا توجد نتائج في تقرير تحميلات السيارات')",
            ],
            [
                'path' => 'Filament/Resources/VehicleExpenseReports/Tables/VehicleExpenseReportsTable.php',
                'sections' => [
                    "Section::make('الفترة والتصنيف')",
                    "Section::make('السيارة ونطاق التشغيل')",
                    "Section::make('فريق التوزيع')",
                ],
                'empty_heading' => "emptyStateHeading('لا توجد نتائج في تقرير مصاريف السيارات')",
            ],
        ];

        foreach ($reports as $report) {
            $table = file_get_contents(app_path($report['path']));

            $this->assertStringContainsString('FiltersLayout::Modal', $table);

            foreach ($report['sections'] as $section) {
                $this->assertStringContainsString($section, $table);
            }

            $this->assertStringContainsString('filtersTriggerAction', $table);
            $this->assertStringContainsString('filtersApplyAction', $table);
            $this->assertStringContainsString('FiltersResetActionPosition::Footer', $table);
            $this->assertStringContainsString('ColumnManagerLayout::Modal', $table);
            $this->assertStringContainsString('columnManagerColumns(2)', $table);
            $this->assertStringContainsString('columnManagerTriggerAction', $table);
            $this->assertStringContainsString('ColumnManagerResetActionPosition::Footer', $table);
            $this->assertStringContainsString('stackedOnMobile', $table);
            $this->assertStringContainsString('persistSearchInSession', $table);
            $this->assertStringContainsString('persistColumnSearchesInSession', $table);
            $this->assertStringContainsString('persistFiltersInSession', $table);
            $this->assertStringContainsString('persistSortInSession', $table);
            $this->assertStringContainsString('paginationPageOptions([10, 25, 50, 100])', $table);
            $this->assertStringContainsString('defaultPaginationPageOption(25)', $table);
            $this->assertStringContainsString($report['empty_heading'], $table);
        }
    }

    public function test_vehicle_load_report_keeps_existing_filters_printing_and_summaries(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/VehicleLoadReports/Tables/VehicleLoadReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/VehicleLoadReports/Pages/ManageVehicleLoadReports.php'));

        foreach ([
            "Filter::make('load_date')",
            "SelectFilter::make('status')",
            "SelectFilter::make('vehicle_id')",
            "SelectFilter::make('route_id')",
            "SelectFilter::make('driver_id')",
            "SelectFilter::make('sales_representative_id')",
            "SelectFilter::make('from_warehouse_id')",
            "SelectFilter::make('to_warehouse_id')",
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        foreach ([
            "TextColumn::make('total_quantity')",
            "TextColumn::make('total_cost')",
            'Count::make()',
            'Sum::make()',
        ] as $summaryElement) {
            $this->assertStringContainsString($summaryElement, $table);
        }

        $this->assertStringContainsString("'reports.vehicle-loads.print'", $table);
        $this->assertStringContainsString("'reports.vehicle-loads.print-filtered'", $page);
        $this->assertStringContainsString('->summaries(', $table);
        $this->assertStringContainsString('allTableCondition: true', $table);
    }

    public function test_vehicle_expense_report_keeps_existing_filters_printing_and_financial_summaries(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/VehicleExpenseReports/Tables/VehicleExpenseReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/VehicleExpenseReports/Pages/ManageVehicleExpenseReports.php'));
        $resource = file_get_contents(app_path('Filament/Resources/VehicleExpenseReports/VehicleExpenseReportResource.php'));

        foreach ([
            "Filter::make('expense_date')",
            "SelectFilter::make('vehicle_id')",
            "SelectFilter::make('warehouse_id')",
            "SelectFilter::make('route_id')",
            "SelectFilter::make('driver_id')",
            "SelectFilter::make('sales_representative_id')",
            "SelectFilter::make('expense_type')",
            "SelectFilter::make('payment_method')",
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        $this->assertStringContainsString("TextColumn::make('amount')", $table);
        $this->assertStringContainsString("->where('payment_method', 'cash')", $table);
        $this->assertStringContainsString("->where('payment_method', '!=', 'cash')", $table);
        $this->assertStringContainsString("'reports.vehicle-expenses.print'", $table);
        $this->assertStringContainsString("'reports.vehicle-expenses.print-filtered'", $page);
        $this->assertStringContainsString("->where('status', 'approved')", $resource);
        $this->assertStringContainsString('->summaries(', $table);
        $this->assertStringContainsString('allTableCondition: true', $table);
    }

    public function test_secondary_vehicle_operation_columns_remain_available_but_hidden_by_default(): void
    {
        $reports = [
            'Filament/Resources/VehicleLoadReports/Tables/VehicleLoadReportsTable.php' => [
                'driver.name',
                'salesRepresentative.name',
                'toWarehouse.name',
                'items_count',
                'approved_at',
            ],
            'Filament/Resources/VehicleExpenseReports/Tables/VehicleExpenseReportsTable.php' => [
                'route.name',
                'driver.name',
                'salesRepresentative.name',
                'approvedBy.name',
                'approved_at',
                'receipt_path',
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
